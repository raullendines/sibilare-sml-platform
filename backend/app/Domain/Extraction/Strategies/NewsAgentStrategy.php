<?php

namespace App\Domain\Extraction\Strategies;

use App\Domain\Extraction\Contracts\ApifyAgentStrategy;
use App\Domain\Extraction\Data\NormalizedPlatformPost;
use App\Domain\Extraction\Strategies\Concerns\NormalizesApifyItems;
use App\Models\ApifyAgent;
use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;

class NewsAgentStrategy implements ApifyAgentStrategy
{
    use NormalizesApifyItems;

    public function buildInput(ExtractionConfig $config, ExtractionJob $job, ApifyAgent $agent): array
    {
        return $this->withActorOptions([
            'queries' => $config->search_query,
            'maxPagesPerQuery' => max(1, (int) ceil($config->max_posts_per_run / 10)),
            'countryCode' => 'es',
            'afterDate' => $job->fetch_start?->format('Y-m-d'),
            'beforeDate' => $job->fetch_end?->format('Y-m-d'),
            'includeUnfilteredResults' => false,
            'mobileResults' => false,
            'saveHtml' => false,
        ], $agent->actor_options ?? []);
    }

    public function normalize(array $item): ?NormalizedPlatformPost
    {
        $url = $this->firstString($item, ['url', 'link']);
        $externalId = $this->firstString($item, ['id']) ?? ($url !== null ? hash('sha256', $url) : null);

        if ($externalId === null) {
            return null;
        }

        return new NormalizedPlatformPost(
            externalId: $externalId,
            authorHandle: $this->firstString($item, ['domain', 'displayedUrl']),
            authorName: $this->firstString($item, ['publisher', 'source', 'domain']),
            contentText: $this->firstString($item, ['title', 'description', 'snippet']),
            url: $url,
            postedAt: $this->firstDate($item, ['publishedAt', 'date', 'publishedDate']),
            languageCode: $this->firstString($item, ['language', 'lang']),
            mediaUrls: $this->mediaUrls($item, ['imageUrl', 'thumbnailUrl']),
            metrics: [],
            rawPayload: $item,
        );
    }
}
