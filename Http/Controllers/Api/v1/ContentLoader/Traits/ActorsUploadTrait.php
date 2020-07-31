<?php

namespace App\Http\Controllers\Api\v1\ContentLoader\Traits;

use App\Models\Actor;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait ActorsUploadTrait
{
    public function uploadNewActors()
    {
        //удалить предыдущего/их автора/ов
        $this->model->clearActors();

        //загружаем авторов, хотя бы один автор всегда существует при редактировании
        if ($this->request->has('actors')) {

            $actors = $this->validatedActors(); //валидируем массив

            $this->validateActorsSumnWeight($this->validator, $actors); //валидиуем доли вклада акторов

            $this->saveActors($actors);

        } else {
            $this->createActor();
        }

        $this->model->load('actors');

        return $this;
    }

    public function paramsExists(array $params)
    {
        return $params && is_array($params);
    }

    public function validatedActors()
    {
        $actors = Arr::get($this->validator->validated(), 'actors');

        $this->validator->after(function ($validator) use ($actors) {
            if (!$this->paramsExists($actors)) {
                $validator->errors()
                    ->add('actors', trans('models/actor.validation.error'));
            }
        });

        if ($this->validator->fails()) {
            return new \Illuminate\Validation\ValidationException($this->validator);
        }

        return $actors;
    }

    public function validateActorsSumnWeight(\Illuminate\Validation\Validator $validator, array $actors)
    {
        //делим акторов на группы по типу прав и валидируем отдельно
        $actorRights = array_keys(config('business.deposit.actors.rights'));

        foreach ($actorRights as $rightType) {

            $actorsCollection = collect($actors)->where('rights', $rightType);

            if ($actorsCollection->count()) {

                $isStringFormat = $this->validateStringFormat($validator, $actorsCollection, $rightType);

                if (!$isStringFormat) {
                    $this->validateFloatValues($validator, $actorsCollection, $rightType);
                } else {
                    $this->validateStringValues($validator, $actorsCollection, $rightType);
                }
            }
        }

        return $this;
    }

    public function validateStringFormat(\Illuminate\Validation\Validator $validator, $actorsCollection, string $rightType)
    {
        $contributionWeights = $actorsCollection->pluck('contributionWeight');

        $grouped = $contributionWeights->groupBy(function ($value) {
            return Str::contains($value, '/');
        });

        if ($grouped->count() > 1) {
            $validator->errors()->add('actors',
                trans('models/actor.validation.contribution_weight_wrong_format', [
                        'rightType' => $rightType,
                    ]
                ));

            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return array_key_first($grouped->toArray());
    }

    public function validateFloatValues(\Illuminate\Validation\Validator $validator, $actorsCollection, string $rightType)
    {
        $sumContributionWeight = $actorsCollection->map(function ($actor) {
            return floatval($actor['contributionWeight']);
        })->sum();

        //сумма не меньше 1 и не больше
        if ($sumContributionWeight != 1) {
            $validator->errors()->add('actors',
                trans('models/actor.validation.sum_contribution_weight_val', [
                        'weight' => $sumContributionWeight,
                        'rightType' => $rightType,
                    ]
                ));

            throw new \Illuminate\Validation\ValidationException($validator);
        }
    }

    public function validateStringValues(\Illuminate\Validation\Validator $validator, $actorsCollection, string $rightType)
    {
        if (!$this->checkValidStringValues($actorsCollection)) {
            $validator->errors()->add('actors',
                trans('models/actor.validation.sum_contribution_weight_str', [
                        'rightType' => $rightType,
                    ]
                ));

            throw new \Illuminate\Validation\ValidationException($validator);
        };
    }

    public function checkValidStringValues($actorsCollection)
    {
        $sumContributionWeight = $actorsCollection->transform(function ($actor) {
            $array = explode('/', $actor['contributionWeight']);

            return [
                'numerator' => intval(Arr::first($array)),
                'denominator' => intval(Arr::last($array)),
            ];
        });

        foreach ($sumContributionWeight as $key => $value) {
            $collect = $sumContributionWeight->except($key)->pluck('denominator');

            $multiplier = 1;
            foreach ($collect as $item) {
                $multiplier *= $item;
            }

            $array = $sumContributionWeight->get($key);
            $col[] = Arr::add($array, 'multiplier', $multiplier);
        }

        $first = $col[0]['denominator'] * $col[0]['multiplier'];

        $second = collect($col)->transform(function ($item) {
            return $item['numerator'] *= $item['multiplier'];
        })->sum();

        return $first === $second;
    }

    public function saveActors(array $actors)
    {
        $related = collect($actors)->map(function ($actor) {
            return Actor::create($actor);
        });

        return $this->model->actors()->saveMany($related);
    }

    public function createActor()
    {
        $actor = (new Actor())->createFromAuthUser();
        return $this->model->actors()->save($actor);
    }
}
