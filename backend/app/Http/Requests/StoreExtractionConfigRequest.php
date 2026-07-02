<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExtractionConfigRequest extends FormRequest
{
    use AuthorizesClientRoles;

    public function authorize(): bool
    {
        return $this->clientUserHasRole(['owner', 'admin', 'editor']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = $client instanceof Client ? $client->id : null;

        return [
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('client_id', $clientId)->where('status', 'active')],
            'brand_id' => ['required', 'uuid', Rule::exists('brands', 'id')->where('client_id', $clientId)],
            'platform_id' => ['required', 'uuid', Rule::exists('platforms', 'id')],
            'search_query' => ['required', 'string', 'max:2000'],
            'frequency' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'retroactive_days' => ['nullable', 'integer', Rule::in([3])],
            'max_posts_per_run' => ['required', 'integer', 'min:1', 'max:10000'],
            'selection_strategy' => ['nullable', 'string', Rule::in(['most_relevant', 'most_recent', 'engagement_weighted'])],
            'cost_limit_per_run' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $projectId = $this->input('project_id');
            $brandId = $this->input('brand_id');

            if (is_string($projectId) && is_string($brandId) && ! DB::table('project_brands')
                ->where('project_id', $projectId)
                ->where('brand_id', $brandId)
                ->exists()) {
                $validator->errors()->add('brand_id', 'The selected brand is not attached to the project.');
            }
        });
    }
}
