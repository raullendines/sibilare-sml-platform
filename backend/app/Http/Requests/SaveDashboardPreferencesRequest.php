<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDashboardPreferencesRequest extends FormRequest
{
    use AuthorizesClientRoles;

    public function authorize(): bool
    {
        return $this->clientUserHasRole(['owner', 'admin', 'editor', 'viewer']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = $client instanceof Client ? $client->id : null;

        return [
            'filter_values' => ['present', 'array'],
            'filter_values.date_range' => ['sometimes', 'nullable', Rule::in(['7d', '30d', '90d'])],
            'filter_values.brand_ids' => ['sometimes', 'array'],
            'filter_values.brand_ids.*' => ['uuid', Rule::exists('brands', 'id')->where('client_id', $clientId)],
            'filter_values.platform_ids' => ['sometimes', 'array'],
            'filter_values.platform_ids.*' => ['uuid', Rule::exists('client_platforms', 'platform_id')->where('client_id', $clientId)->where('enabled', true)],
            'filter_values.brand_type' => ['sometimes', 'nullable', Rule::in(['own_brand', 'own_subbrand', 'competitor', 'competitor_subbrand'])],
            'filter_values.relevance' => ['sometimes', 'nullable', 'boolean'],
            'filter_values.search' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
