<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardWidgetResource extends JsonResource
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
            'widget_template_id' => $this->widget_template_id,
            'widget_type' => $this->widget_type,
            'visualization_type' => $this->visualization_type,
            'metric_code' => $this->metric_code,
            'title' => $this->title,
            'description' => $this->description,
            'grid_x' => $this->grid_x,
            'grid_y' => $this->grid_y,
            'grid_width' => $this->grid_width,
            'grid_height' => $this->grid_height,
            'min_width' => $this->min_width,
            'min_height' => $this->min_height,
            'sort_order' => $this->sort_order,
            'config' => $this->config,
            'filters' => $this->filters,
            'is_visible' => $this->is_visible,
            'template' => WidgetTemplateResource::make($this->whenLoaded('template')),
            'metric' => MetricDefinitionResource::make($this->whenLoaded('metric')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
