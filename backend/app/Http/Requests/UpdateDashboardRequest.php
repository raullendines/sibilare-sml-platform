<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash:ascii'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['draft', 'archived'])],
            'is_default' => ['sometimes', 'boolean'],
            'grid_columns' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'layout_mode' => ['sometimes', 'required', 'string', Rule::in(['freeform', 'guided'])],
        ];
    }
}
