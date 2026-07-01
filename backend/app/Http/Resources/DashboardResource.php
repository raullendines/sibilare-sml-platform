<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'is_default' => $this->is_default,
            'grid_columns' => $this->grid_columns,
            'layout_mode' => $this->layout_mode,
            'current_version_number' => $this->current_version_number,
            'widgets_count' => $this->whenCounted('widgets'),
            'widgets' => DashboardWidgetResource::collection($this->whenLoaded('widgets')),
            'filters' => DashboardFilterResource::collection($this->whenLoaded('filters')),
            'preferences_supported' => $this->when(
                array_key_exists('preferences_supported', $this->resource->getAttributes()),
                fn (): bool => (bool) $this->resource->getAttribute('preferences_supported'),
            ),
            'viewer_preferences' => $this->when(
                $this->relationLoaded('currentUserPreference'),
                fn (): ?array => $this->currentUserPreference === null
                    ? null
                    : (new DashboardUserPreferenceResource($this->currentUserPreference))->resolve()
            ),
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
