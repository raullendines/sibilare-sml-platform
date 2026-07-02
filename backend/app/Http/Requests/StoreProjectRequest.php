<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('projects', 'slug')->where('client_id', $clientId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'archived'])],
            'default_data_frequency' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'brand_ids' => ['sometimes', 'array'],
            'brand_ids.*' => ['uuid', 'distinct', Rule::exists('brands', 'id')->where('client_id', $clientId)],
        ];
    }
}
