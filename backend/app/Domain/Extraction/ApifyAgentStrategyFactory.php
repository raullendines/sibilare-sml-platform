<?php

namespace App\Domain\Extraction;

use App\Domain\Extraction\Contracts\ApifyAgentStrategy;
use App\Domain\Extraction\Strategies\InstagramAgentStrategy;
use App\Domain\Extraction\Strategies\NewsAgentStrategy;
use App\Domain\Extraction\Strategies\XAgentStrategy;
use App\Domain\Extraction\Strategies\YouTubeAgentStrategy;
use InvalidArgumentException;

class ApifyAgentStrategyFactory
{
    public function forPlatform(string $platformCode): ApifyAgentStrategy
    {
        return match ($platformCode) {
            'x' => app(XAgentStrategy::class),
            'instagram' => app(InstagramAgentStrategy::class),
            'youtube' => app(YouTubeAgentStrategy::class),
            'news' => app(NewsAgentStrategy::class),
            'tiktok' => throw new InvalidArgumentException('TikTok extraction remains disabled until its actor canary passes.'),
            default => throw new InvalidArgumentException("No Apify strategy exists for platform {$platformCode}."),
        };
    }
}
