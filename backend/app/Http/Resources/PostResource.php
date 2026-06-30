<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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
            'platform_post_id' => $this->platform_post_id,
            'extraction_run_id' => $this->extraction_run_id,
            'matched_query' => $this->matched_query,
            'match_type' => $this->match_type,
            'is_relevant_candidate' => $this->is_relevant_candidate,
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'platform_post' => PlatformPostResource::make($this->whenLoaded('platformPost')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
