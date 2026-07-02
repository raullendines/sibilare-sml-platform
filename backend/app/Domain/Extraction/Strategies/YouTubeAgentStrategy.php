<?php

namespace App\Domain\Extraction\Strategies;

use App\Domain\Extraction\Contracts\ApifyAgentStrategy;
use App\Domain\Extraction\Data\NormalizedPlatformPost;
use App\Domain\Extraction\Strategies\Concerns\NormalizesApifyItems;
use App\Models\ApifyAgent;
use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;

class YouTubeAgentStrategy implements ApifyAgentStrategy
{
    use NormalizesApifyItems;

    public function buildInput(ExtractionConfig $config, ExtractionJob $job, ApifyAgent $agent): array
    {
        return $this->withActorOptions([
            'query' => $config->search_query,
            'type' => 'video',
            'maxResults' => min($config->max_posts_per_run, $agent->max_items_limit ?? PHP_INT_MAX),
            'sortBy' => $config->selection_strategy === 'most_recent' ? 'date' : 'relevance',
            'uploadDate' => match ($job->frequency_type) {
                'daily' => 'today',
                'monthly' => 'month',
                default => 'week',
            },
            'videoDepthDetails' => 'basic',
        ], $agent->actor_options ?? []);
    }

    public function normalize(array $item): ?NormalizedPlatformPost
    {
        $externalId = $this->firstString($item, ['id', 'videoId', 'video.id']);

        if ($externalId === null) {
            return null;
        }

        return new NormalizedPlatformPost(
            externalId: $externalId,
            authorHandle: $this->firstString($item, ['channelHandle', 'channel.handle']),
            authorName: $this->firstString($item, ['channelName', 'channelTitle', 'channel.name']),
            contentText: $this->firstString($item, ['title', 'description', 'text']),
            url: $this->firstString($item, ['url', 'videoUrl']),
            postedAt: $this->firstDate($item, ['publishedAt', 'date', 'uploadedAt']),
            languageCode: $this->firstString($item, ['language', 'defaultLanguage']),
            mediaUrls: $this->mediaUrls($item, ['thumbnailUrl', 'thumbnail', 'thumbnails']),
            metrics: $this->metrics($item, [
                'views' => ['viewCount', 'views'],
                'likes' => ['likeCount', 'likes'],
                'comments' => ['commentCount', 'comments'],
            ]),
            rawPayload: $item,
        );
    }
}
