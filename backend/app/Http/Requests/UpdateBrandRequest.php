<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
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
            'parent_brand_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('brands', 'id')->where('client_id', $clientId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'brand_type' => ['sometimes', 'required', 'string', Rule::in(['own_brand', 'own_subbrand', 'competitor', 'competitor_subbrand'])],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
