<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetricDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'source_domain' => $this->source_domain,
            'value_type' => $this->value_type,
            'default_aggregation' => $this->default_aggregation,
            'default_visualization_type' => $this->default_visualization_type,
            'config_schema' => $this->config_schema,
        ];
    }
}
