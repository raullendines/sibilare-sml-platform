<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtractionConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'brand_id' => $this->brand_id,
            'platform_id' => $this->platform_id,
            'search_query' => $this->search_query,
            'frequency' => $this->frequency,
            'retroactive_days' => $this->retroactive_days,
            'max_posts_per_run' => $this->max_posts_per_run,
            'selection_strategy' => $this->selection_strategy,
            'cost_limit_per_run' => $this->cost_limit_per_run,
            'is_active' => $this->is_active,
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'platform' => PlatformResource::make($this->whenLoaded('platform')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
