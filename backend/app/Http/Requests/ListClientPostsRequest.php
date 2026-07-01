<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListClientPostsRequest extends FormRequest
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
            'brand_id' => ['sometimes', 'uuid', Rule::exists('brands', 'id')->where('client_id', $clientId)],
            'platform_id' => ['sometimes', 'uuid', Rule::exists('client_platforms', 'platform_id')->where('client_id', $clientId)->where('enabled', true)],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['sometimes', 'string', 'max:200'],
            'brand_type' => ['sometimes', 'string', Rule::in(['own_brand', 'own_subbrand', 'competitor', 'competitor_subbrand'])],
            'relevance' => ['sometimes', Rule::in(['true', 'false', '1', '0'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = $this->validated();

        if ($this->has('relevance')) {
            $filters['relevance'] = $this->boolean('relevance');
        }

        return $filters;
    }
}
