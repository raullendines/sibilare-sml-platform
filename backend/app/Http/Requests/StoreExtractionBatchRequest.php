<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtractionBatchRequest extends FormRequest
{
    use AuthorizesClientRoles;

    public function authorize(): bool
    {
        return $this->clientUserHasRole(['owner', 'admin', 'editor']);
    }

    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = $client instanceof Client ? $client->id : null;

        return [
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('client_id', $clientId)->where('status', 'active')],
            'config_ids' => ['nullable', 'array', 'min:1'],
            'config_ids.*' => ['uuid', Rule::exists('extraction_configs', 'id')->where('client_id', $clientId)->where('is_active', true)],
        ];
    }
}
