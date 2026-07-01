<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['sometimes', 'boolean'],
            'grid_columns' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'layout_mode' => ['sometimes', 'string', Rule::in(['freeform', 'guided'])],
            'starter_template' => ['sometimes', 'string', Rule::in(['blank', 'social-listening-overview'])],
        ];
    }
}
