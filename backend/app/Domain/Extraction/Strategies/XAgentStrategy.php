<?php

namespace App\Domain\Extraction\Strategies;

use App\Domain\Extraction\Contracts\ApifyAgentStrategy;
use App\Domain\Extraction\Data\NormalizedPlatformPost;
use App\Domain\Extraction\Strategies\Concerns\NormalizesApifyItems;
use App\Models\ApifyAgent;
use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;

class XAgentStrategy implements ApifyAgentStrategy
{
    use NormalizesApifyItems;

    public function buildInput(ExtractionConfig $config, ExtractionJob $job, ApifyAgent $agent): array
    {
        return $this->withActorOptions([
            'searchTerms' => [$config->search_query],
            'maxItems' => min($config->max_posts_per_run, $agent->max_items_limit ?? PHP_INT_MAX),
            'sort' => 'Latest',
            'maxRequestRetries' => 3,
        ], $agent->actor_options ?? []);
    }

    public function normalize(array $item): ?NormalizedPlatformPost
    {
        $externalId = $this->firstString($item, ['id', 'tweetId', 'rest_id']);

        if ($externalId === null) {
            return null;
        }

        return new NormalizedPlatformPost(
            externalId: $externalId,
            authorHandle: $this->firstString($item, ['author.userName', 'author.username', 'user.screen_name', 'username']),
            authorName: $this->firstString($item, ['author.name', 'user.name', 'name']),
            contentText: $this->firstString($item, ['text', 'fullText', 'full_text']),
            url: $this->firstString($item, ['url', 'twitterUrl', 'tweetUrl']),
            postedAt: $this->firstDate($item, ['createdAt', 'created_at', 'date']),
            languageCode: $this->firstString($item, ['lang', 'language']),
            mediaUrls: $this->mediaUrls($item, ['media', 'images', 'extendedEntities.media']),
            metrics: $this->metrics($item, [
                'likes' => ['likeCount', 'favorite_count'],
                'reposts' => ['retweetCount', 'retweet_count'],
                'replies' => ['replyCount', 'reply_count'],
                'views' => ['viewCount', 'views'],
            ]),
            rawPayload: $item,
        );
    }
}
