<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use App\Models\Client;
use App\Models\ExtractionConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateExtractionConfigRequest extends FormRequest
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
            'project_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('projects', 'id')->where('client_id', $clientId)->where('status', 'active')],
            'brand_id' => ['sometimes', 'required', 'uuid', Rule::exists('brands', 'id')->where('client_id', $clientId)],
            'platform_id' => ['sometimes', 'required', 'uuid', Rule::exists('platforms', 'id')],
            'search_query' => ['sometimes', 'required', 'string', 'max:2000'],
            'frequency' => ['sometimes', 'nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'retroactive_days' => ['sometimes', 'required', 'integer', Rule::in([3])],
            'max_posts_per_run' => ['sometimes', 'required', 'integer', 'min:1', 'max:10000'],
            'selection_strategy' => ['sometimes', 'required', 'string', Rule::in(['most_relevant', 'most_recent', 'engagement_weighted'])],
            'cost_limit_per_run' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $config = $this->route('extractionConfig');

            if (! $config instanceof ExtractionConfig) {
                return;
            }

            $projectId = $this->exists('project_id') ? $this->input('project_id') : $config->project_id;
            $brandId = $this->input('brand_id', $config->brand_id);

            if (is_string($projectId) && is_string($brandId) && ! DB::table('project_brands')
                ->where('project_id', $projectId)
                ->where('brand_id', $brandId)
                ->exists()) {
                $validator->errors()->add('brand_id', 'The selected brand is not attached to the project.');
            }
        });
    }
}
