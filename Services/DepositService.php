<?php

namespace App\Services;

use App\Http\Controllers\Api\v1\ContentLoader\AlgorithmLoader;
use App\Http\Controllers\Api\v1\ContentLoader\ContentLoader;
use App\Http\Controllers\Api\v1\ContentLoader\ImplementationLoader;
use App\Http\Controllers\Api\v1\ContentLoader\ScreensLoader;
use App\Http\Controllers\Api\v1\ContentLoader\Traits\ActorsUploadTrait;
use App\Models\Deposit;
use App\Models\DepositDraft;
use App\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use function Clue\StreamFilter\append;

class DepositService
{
    use ActorsUploadTrait;

    protected $model;

    protected $query;

    protected $request;

    protected $validator;


    public function __construct()
    {
        $this->model = new Deposit;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setModel($deposit)
    {
        $this->model = $deposit;
        return $this;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function setQuery($query)
    {
        $this->query = $query;
        return $this;
    }

    public function getList($userId)
    {
        $query = $this->model->whereUserId($userId)->latest('updated_at');

        $query = $this->query->get('filter')
            ? QueryBuilder
                ::for($query)
                ->allowedFilters(
                    array_merge($this->getFilters(), [
                        AllowedFilter::exact('project.id')
                    ]))
                ->allowedSorts($this->getSorts())
            : $query;

        $collection = $query->get()->transform(function ($deposit) {
            return $deposit->getAdditionTypeFields();
        });

        return $collection;
    }

    public function getFilters()
    {
        return $this->getQueryParams(
                $this->model->getAllowedFilterables(),
                $this->query->get('filter') ?? []
            ) ?? [];
    }

    public function getQueryParams(array $params, array $queryParams)
    {
        return array_values(array_intersect($params, array_keys($queryParams)));
    }

    public function getSorts()
    {
        return $this->getQueryParams(
                $this->model->getAllowedSortables(),
                explode(',', $this->query->get('sort')) ?? []
            ) ?? [];
    }

    public function getDetails($id)
    {
        $deposit = $this->model->find($id);
        return $deposit ? $deposit->getAdditionTypeFields() : null;
    }

    public function storeDeposit()
    {
        $this->initValidator();

        if ($this->validator->fails()) {
            return new \Illuminate\Validation\ValidationException($this->validator);
        }

        try {
            \DB::transaction(function () {

                $this->model = new DepositDraft();
                $this->model->setDefaultAttributes()
                    ->fill($this->validator->validated())
                    ->save();

                $this->uploadProject();
                $this->uploadNewActors();
                $this->uploadNewFiles();
            });

        } catch (\Exception $exception) {
            Log::error("Exception Message: " . $exception->getMessage() . "; file: " .
                $exception->getFile() . "; line: " . $exception->getLine(), ['context' => json_encode($this->model)]);
            return $exception;
        }

        return $this->model;
    }

    public function initValidator()
    {
        $this->validator = Validator::make(
            $this->request->all(),
            $this->request->rules(),
            $this->request->messages()
        );

        return $this;
    }

    public function uploadProject()
    {
        if (!$projectId = $this->request->input('project.id')) {
            return $this;
        }

        if (!$project = Project::find($projectId)) {
            return new ModelNotFoundException(trans('messages.api.not_found', ['model' => 'Project']), 405);
        }

        $this->model->project_id = $project->id;
        $this->model->save();

        $this->model->load('project');

        return $this;
    }

    public function uploadNewFiles()
    {
        return
            $this
                ->removeAllFiles()
                ->uploadContent();
    }

    public function uploadContent()
    {
        //загружаем ОИС
        if ($this->request->has('content') && $this->request->content) {

            $loader = new ContentLoader();

            switch ($this->model->type) {
                case 'implementation':
                    $loader->setLoader(new ImplementationLoader($this->model, $this->request));
                    $loader->getContent($this->validator);

                    $this->model->append('content', 'language');
                    break;
                case 'algorithm':
                    $loader->setLoader(new AlgorithmLoader($this->model, $this->request));
                    $loader->getContent($this->validator);

                    $this->model->append('content');
                    break;
                case 'screens':
                    $loader->setLoader(new ScreensLoader($this->model, $this->request));
                    $loader->getContent($this->validator);
                    break;
                default:
                    break;
            }

            $this->model->load('files');
        }

        $this->model->refresh();
    }

    //-------------------------------------------------------------------------------files------------------------------

    public function removeAllFiles()
    {
        $this->model->clearFiles();
        $this->model->refresh();

        return $this;
    }

    public function updateDeposit()
    {
        $this->validator = Validator::make(
            array_merge($this->model->toArray(), $this->request->input()),
            $this->request->rules()
        );

        if ($this->validator->fails()) {
            return new \Illuminate\Validation\ValidationException($this->validator);
        }

        try {
            \DB::transaction(function () {

                $this->model
                    ->update($this->validator->validated());

                $this->uploadProject();
                $this->uploadNewActors();
                $this->uploadNewFiles();
            });
        } catch (\Exception $exception) {
            return $exception;
        }

        return $this->model;
    }

    public function deleteDeposit()
    {
        $this->model
            ->clearRelations()
            ->delete();

        return true;
    }
}
