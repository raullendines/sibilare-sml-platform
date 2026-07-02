<?php

namespace App\Domain\Extraction\Strategies;

use App\Domain\Extraction\Contracts\ApifyAgentStrategy;
use App\Domain\Extraction\Data\NormalizedPlatformPost;
use App\Domain\Extraction\Strategies\Concerns\NormalizesApifyItems;
use App\Models\ApifyAgent;
use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;

class InstagramAgentStrategy implements ApifyAgentStrategy
{
    use NormalizesApifyItems;

    public function buildInput(ExtractionConfig $config, ExtractionJob $job, ApifyAgent $agent): array
    {
        preg_match('/#([\pL\pN_.-]+)/u', $config->search_query, $hashtagMatch);
        $hashtag = $hashtagMatch[1]
            ?? ltrim(trim((string) preg_split('/\s+OR\s+/i', $config->search_query)[0]), '#@');

        return $this->withActorOptions([
            'hashtag' => $hashtag,
            'maxItems' => min($config->max_posts_per_run, $agent->max_items_limit ?? PHP_INT_MAX),
        ], $agent->actor_options ?? []);
    }

    public function normalize(array $item): ?NormalizedPlatformPost
    {
        $externalId = $this->firstString($item, ['id', 'shortCode', 'shortcode', 'code']);

        if ($externalId === null) {
            return null;
        }

        return new NormalizedPlatformPost(
            externalId: $externalId,
            authorHandle: $this->firstString($item, ['ownerUsername', 'owner.username', 'username']),
            authorName: $this->firstString($item, ['ownerFullName', 'owner.fullName', 'fullName']),
            contentText: $this->firstString($item, ['caption', 'text', 'description']),
            url: $this->firstString($item, ['url', 'postUrl']),
            postedAt: $this->firstDate($item, ['timestamp', 'takenAt', 'date']),
            languageCode: $this->firstString($item, ['language', 'lang']),
            mediaUrls: $this->mediaUrls($item, ['displayUrl', 'images', 'videoUrl', 'childPosts']),
            metrics: $this->metrics($item, [
                'likes' => ['likesCount', 'likeCount'],
                'comments' => ['commentsCount', 'commentCount'],
                'views' => ['videoViewCount', 'videoPlayCount'],
            ]),
            rawPayload: $item,
        );
    }
}
