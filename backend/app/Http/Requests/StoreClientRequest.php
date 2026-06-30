<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('clients', 'slug')],
            'status' => ['nullable', 'string', Rule::in(['onboarding', 'active', 'paused', 'churned'])],
            'industry' => ['nullable', 'string', 'max:255'],
            'default_locale' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'timezone'],
        ];
    }
}
