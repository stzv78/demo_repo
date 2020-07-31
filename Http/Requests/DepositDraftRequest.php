<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepositDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $types = array_keys(config('business.deposit.type'));

        $actorRights = array_keys(config('business.deposit.actors.rights'));
        $actorStatuses = array_keys(config('business.deposit.actors.statuses'));
        $actorTerritory = array_keys(config('business.deposit.actors.territories'));

        return [
            'name' => 'required|string|max:191',
            'type' => 'required|string|in:' . implode(',', $types),
            'description' => 'nullable|string|max:191',
            'project' => 'sometimes|required',
            'project.id' => 'required|numeric|exists:projects,id',
            'actors.*' => 'sometimes|required',
            'actors.*.name' => 'required|string|max:191',
            'actors.*.status' => 'required|string|max:50|in:' . implode(',', $actorStatuses),
            'actors.*.rights' => 'required|string|max:100|in:' . implode(',', $actorRights),
            'actors.*.contributionWeight' => 'required|string|max:10|min:1',
            'actors.*.territory' => 'required|array',
            'actors.*.territory.*' => 'required|string|max:5|in:' . implode(',', $actorTerritory),
            'actors.*.dateFrom' => 'required|string',
            'content' => 'sometimes|required',
        ];
    }

    public function messages()
    {
        return [];
    }

}
