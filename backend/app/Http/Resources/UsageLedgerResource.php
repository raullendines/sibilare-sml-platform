<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsageLedgerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'usage_type' => $this->usage_type,
            'source_table' => $this->source_table,
            'source_id' => $this->source_id,
            'brand_id' => $this->brand_id,
            'platform_id' => $this->platform_id,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'cost_amount' => $this->cost_amount,
            'currency' => $this->currency,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'metadata' => $this->metadata,
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'platform' => PlatformResource::make($this->whenLoaded('platform')),
        ];
    }
}
