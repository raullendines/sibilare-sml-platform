<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WidgetTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'widget_type' => $this->widget_type,
            'metric_code' => $this->metric_code,
            'default_title' => $this->default_title,
            'default_visualization_type' => $this->default_visualization_type,
            'default_config' => $this->default_config,
            'default_width' => $this->default_width,
            'default_height' => $this->default_height,
            'min_width' => $this->min_width,
            'min_height' => $this->min_height,
            'metric' => MetricDefinitionResource::make($this->whenLoaded('metric')),
        ];
    }
}
