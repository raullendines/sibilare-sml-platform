<?php

namespace App\Domain\Extraction\Actions;

use App\Domain\Extraction\ApifyAgentStrategyFactory;
use App\Models\ExtractionRun;
use App\Models\PlatformPost;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

class StoreNormalizedExtractionItems
{
    public function __construct(private readonly ApifyAgentStrategyFactory $strategies) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{fetched: int, stored: int, discarded: int}
     */
    public function handle(ExtractionRun $run, array $items): array
    {
        $run->loadMissing(['extractionConfig.platform', 'extractionConfig.project']);
        $config = $run->extractionConfig;
        $strategy = $this->strategies->forPlatform($config->platform->code);
        $stored = 0;
        $discarded = 0;
        $seenExternalIds = [];

        foreach ($items as $item) {
            $normalized = $strategy->normalize($item);

            if ($normalized === null || isset($seenExternalIds[$normalized->externalId]) || $normalized->postedAt === null
                || $normalized->postedAt->lessThan($run->fetch_start)
                || ! $normalized->postedAt->lessThan($run->fetch_end)) {
                $discarded++;

                continue;
            }

            $seenExternalIds[$normalized->externalId] = true;

            DB::transaction(function () use ($run, $config, $normalized, &$stored): void {
                $platformPost = PlatformPost::query()->updateOrCreate(
                    [
                        'platform_id' => $run->platform_id,
                        'external_id' => $normalized->externalId,
                    ],
                    [
                        'author_handle' => $normalized->authorHandle,
                        'author_name' => $normalized->authorName,
                        'content_text' => $normalized->contentText,
                        'url' => $normalized->url,
                        'posted_at' => $normalized->postedAt,
                        'language_code' => $normalized->languageCode,
                        'media_urls' => $normalized->mediaUrls,
                        'metrics' => $normalized->metrics,
                        'raw_payload' => $normalized->rawPayload,
                    ],
                );

                $post = Post::query()->updateOrCreate(
                    [
                        'client_id' => $run->client_id,
                        'brand_id' => $run->brand_id,
                        'platform_post_id' => $platformPost->id,
                    ],
                    [
                        'extraction_run_id' => $run->id,
                        'matched_query' => $config->search_query,
                        'match_type' => str_starts_with((string) $config->brand?->brand_type, 'competitor') ? 'competitor' : 'brand',
                        'is_relevant_candidate' => true,
                    ],
                );

                $projectIds = $config->project_id !== null
                    ? [$config->project_id]
                    : DB::table('project_brands')
                        ->where('client_id', $run->client_id)
                        ->where('brand_id', $run->brand_id)
                        ->pluck('project_id')
                        ->all();

                foreach ($projectIds as $projectId) {
                    DB::table('project_posts')->updateOrInsert(
                        ['project_id' => $projectId, 'post_id' => $post->id],
                        [
                            'client_id' => $run->client_id,
                            'extraction_run_id' => $run->id,
                            'created_at' => now(),
                        ],
                    );
                }

                $stored++;
            }, 3);
        }

        return ['fetched' => count($items), 'stored' => $stored, 'discarded' => $discarded];
    }
}
