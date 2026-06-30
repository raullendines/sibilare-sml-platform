<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
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

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('clients', 'slug')->ignore($client instanceof Client ? $client->id : null),
            ],
            'status' => ['sometimes', 'required', 'string', Rule::in(['onboarding', 'active', 'paused', 'churned'])],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_locale' => ['sometimes', 'required', 'string', 'max:20'],
            'timezone' => ['sometimes', 'required', 'timezone'],
        ];
    }
}
