<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtractionConfigRequest extends FormRequest
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
            'brand_id' => ['required', 'uuid', Rule::exists('brands', 'id')->where('client_id', $clientId)],
            'platform_id' => ['required', 'uuid', Rule::exists('platforms', 'id')],
            'search_query' => ['required', 'string', 'max:2000'],
            'frequency' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'retroactive_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_posts_per_run' => ['required', 'integer', 'min:1', 'max:10000'],
            'selection_strategy' => ['nullable', 'string', Rule::in(['most_relevant', 'most_recent', 'engagement_weighted'])],
            'cost_limit_per_run' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
