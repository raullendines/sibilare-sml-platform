<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardFilterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'dashboard_id' => $this->dashboard_id,
            'field_code' => $this->field_code,
            'label' => $this->label,
            'filter_type' => $this->filter_type,
            'default_value' => $this->default_value,
            'config' => $this->config,
            'sort_order' => $this->sort_order,
            'is_visible' => $this->is_visible,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
