<?php

namespace Tests\Unit;

use App\Domain\Extraction\Strategies\InstagramAgentStrategy;
use App\Domain\Extraction\Strategies\NewsAgentStrategy;
use App\Domain\Extraction\Strategies\XAgentStrategy;
use App\Domain\Extraction\Strategies\YouTubeAgentStrategy;
use App\Models\ApifyAgent;
use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;
use Tests\TestCase;

class ApifyAgentStrategiesTest extends TestCase
{
    public function test_x_strategy_uses_the_aoc_actor_contract_and_normalizes_metrics(): void
    {
        $input = (new XAgentStrategy)->buildInput($this->config('Brand OR @brand'), $this->job(), $this->agent());
        $post = (new XAgentStrategy)->normalize([
            'id' => 'tweet-1',
            'text' => 'Brand mention',
            'createdAt' => '2026-07-01T12:00:00Z',
            'author' => ['userName' => 'customer'],
            'likeCount' => 10,
        ]);

        $this->assertSame(['Brand OR @brand'], $input['searchTerms']);
        $this->assertSame('Latest', $input['sort']);
        $this->assertSame('tweet-1', $post?->externalId);
        $this->assertSame(10, $post?->metrics['likes']);
    }

    public function test_instagram_strategy_extracts_hashtag_and_normalizes_post(): void
    {
        $strategy = new InstagramAgentStrategy;
        $input = $strategy->buildInput($this->config('@brand OR #campaign'), $this->job(), $this->agent());
        $post = $strategy->normalize([
            'shortCode' => 'ig-1',
            'caption' => 'Campaign mention',
            'timestamp' => '2026-07-01T12:00:00Z',
            'ownerUsername' => 'customer',
            'likesCount' => 8,
        ]);

        $this->assertSame('campaign', $input['hashtag']);
        $this->assertSame(100, $input['maxItems']);
        $this->assertSame('ig-1', $post?->externalId);
    }

    public function test_youtube_strategy_adapts_upload_period_and_normalizes_video(): void
    {
        $strategy = new YouTubeAgentStrategy;
        $job = $this->job();
        $job->frequency_type = 'monthly';
        $input = $strategy->buildInput($this->config('Brand review'), $job, $this->agent());
        $post = $strategy->normalize([
            'videoId' => 'video-1',
            'title' => 'Brand review',
            'publishedAt' => '2026-06-20T12:00:00Z',
            'channelName' => 'Reviewer',
        ]);

        $this->assertSame('month', $input['uploadDate']);
        $this->assertSame('video-1', $post?->externalId);
    }

    public function test_news_strategy_sends_exact_dates_and_uses_url_as_stable_identity(): void
    {
        $strategy = new NewsAgentStrategy;
        $input = $strategy->buildInput($this->config('Brand news'), $this->job(), $this->agent());
        $post = $strategy->normalize([
            'url' => 'https://news.example/article',
            'title' => 'Brand news',
            'publishedAt' => '2026-07-01T12:00:00Z',
            'publisher' => 'News Example',
        ]);

        $this->assertSame('2026-06-28', $input['afterDate']);
        $this->assertSame('2026-07-02', $input['beforeDate']);
        $this->assertSame(hash('sha256', 'https://news.example/article'), $post?->externalId);
    }

    private function config(string $query): ExtractionConfig
    {
        return new ExtractionConfig([
            'search_query' => $query,
            'max_posts_per_run' => 100,
            'selection_strategy' => 'most_recent',
        ]);
    }

    private function job(): ExtractionJob
    {
        return new ExtractionJob([
            'frequency_type' => 'daily',
            'fetch_start' => '2026-06-28 00:00:00',
            'fetch_end' => '2026-07-02 00:00:00',
        ]);
    }

    private function agent(): ApifyAgent
    {
        return new ApifyAgent([
            'max_items_limit' => 1000,
            'actor_options' => [],
        ]);
    }
}
