<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesClientRoles;
use Illuminate\Foundation\Http\FormRequest;

class PublishDashboardRequest extends FormRequest
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
        return [];
    }
}
