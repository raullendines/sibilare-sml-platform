<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform_id' => $this->platform_id,
            'external_id' => $this->external_id,
            'author_handle' => $this->author_handle,
            'author_name' => $this->author_name,
            'content_text' => $this->content_text,
            'url' => $this->url,
            'posted_at' => $this->posted_at?->toISOString(),
            'language_code' => $this->language_code,
            'media_urls' => $this->media_urls,
            'metrics' => $this->metrics,
            'platform' => PlatformResource::make($this->whenLoaded('platform')),
        ];
    }
}
