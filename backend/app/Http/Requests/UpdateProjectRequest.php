<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
        $project = $this->route('project');
        $clientId = $client instanceof Client ? $client->id : null;
        $projectId = $project instanceof Project ? $project->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('projects', 'slug')->where('client_id', $clientId)->ignore($projectId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'archived'])],
            'default_data_frequency' => ['sometimes', 'nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'brand_ids' => ['sometimes', 'array'],
            'brand_ids.*' => ['uuid', 'distinct', Rule::exists('brands', 'id')->where('client_id', $clientId)],
        ];
    }
}
