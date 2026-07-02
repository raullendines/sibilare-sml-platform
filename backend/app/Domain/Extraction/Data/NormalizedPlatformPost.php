<?php

namespace App\Domain\Extraction\Data;

use Carbon\CarbonImmutable;

final readonly class NormalizedPlatformPost
{
    /**
     * @param  list<string>  $mediaUrls
     * @param  array<string, int|float|string|null>  $metrics
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $externalId,
        public ?string $authorHandle,
        public ?string $authorName,
        public ?string $contentText,
        public ?string $url,
        public ?CarbonImmutable $postedAt,
        public ?string $languageCode,
        public array $mediaUrls,
        public array $metrics,
        public array $rawPayload,
    ) {}
}
