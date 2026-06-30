<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExtractionConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = $client instanceof Client ? $client->id : null;

        return [
            'brand_id' => ['sometimes', 'required', 'uuid', Rule::exists('brands', 'id')->where('client_id', $clientId)],
            'platform_id' => ['sometimes', 'required', 'uuid', Rule::exists('platforms', 'id')],
            'search_query' => ['sometimes', 'required', 'string', 'max:2000'],
            'frequency' => ['sometimes', 'nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'retroactive_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:365'],
            'max_posts_per_run' => ['sometimes', 'required', 'integer', 'min:1', 'max:10000'],
            'selection_strategy' => ['sometimes', 'required', 'string', Rule::in(['most_relevant', 'most_recent', 'engagement_weighted'])],
            'cost_limit_per_run' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
