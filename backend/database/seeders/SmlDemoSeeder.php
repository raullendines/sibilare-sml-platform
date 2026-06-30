<?php

namespace Database\Seeders;

use App\Models\ApifyAgent;
use App\Models\Brand;
use App\Models\BrandAlias;
use App\Models\BrandPlatformVolumeEstimate;
use App\Models\Client;
use App\Models\ClientBranding;
use App\Models\ClientDataAvailability;
use App\Models\ClientPlanItem;
use App\Models\ClientSubscription;
use App\Models\ClientUser;
use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;
use App\Models\ExtractionRun;
use App\Models\Platform;
use App\Models\PlatformPost;
use App\Models\Post;
use App\Models\UsageLedger;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SmlDemoSeeder extends Seeder
{
    private const CLIENT_SLUG = 'sibilare-demo';

    /**
     * Seed a realistic single-client Social Media Listening demo workspace.
     */
    public function run(): void
    {
        $platforms = $this->seedPlatforms();
        $client = $this->seedClient();
        $clientUser = $this->seedClientUser($client);

        $this->seedCommercialContext($client);
        $this->seedClientPlatforms($client, $platforms);

        $brands = $this->seedBrands($client);
        $this->seedBrandAliases($brands, $platforms);
        $this->seedVolumeEstimates($client, $brands, $platforms);
        $this->seedDataAvailability($client, $brands, $platforms);

        $agents = $this->seedApifyAgents($platforms);
        $configs = $this->seedExtractionConfigs($client, $brands, $platforms);
        $runs = $this->seedExtractionRuns($client, $configs, $agents);
        $posts = $this->seedPosts($client, $brands, $platforms, $runs);
        $this->seedUsageLedger($client, $runs, $posts);

        $message = 'Seeded Sibilare SML demo client with brands, extraction configs, posts, and usage ledger.';

        if ($clientUser === null) {
            $message .= ' Set SML_DEMO_AUTH_USER_ID to a real Supabase auth.users UUID to grant browser API access.';
        }

        $this->command?->info($message);
    }

    /**
     * @return array<string, Platform>
     */
    private function seedPlatforms(): array
    {
        $platformDefinitions = [
            'x' => 'X',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'news' => 'News',
        ];

        $platforms = [];

        foreach ($platformDefinitions as $code => $name) {
            $platforms[$code] = Platform::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }

        return $platforms;
    }

    private function seedClient(): Client
    {
        return Client::query()->updateOrCreate(
            ['slug' => self::CLIENT_SLUG],
            [
                'name' => 'Sibilare Demo',
                'status' => 'active',
                'industry' => 'Social Media Listening',
                'default_locale' => 'es-ES',
                'timezone' => 'Europe/Madrid',
            ],
        );
    }

    private function seedClientUser(Client $client): ?ClientUser
    {
        $authUserId = trim((string) env('SML_DEMO_AUTH_USER_ID', ''));

        if ($authUserId === '') {
            return null;
        }

        if (! Str::isUuid($authUserId)) {
            $this->command?->warn('SML_DEMO_AUTH_USER_ID is not a valid UUID; skipping demo client membership.');

            return null;
        }

        if (! $this->authUserExists($authUserId)) {
            $this->command?->warn('SML_DEMO_AUTH_USER_ID was not found in auth.users; skipping demo client membership.');

            return null;
        }

        return ClientUser::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'auth_user_id' => $authUserId,
            ],
            [
                'role' => 'owner',
                'accepted_at' => Carbon::parse('2026-06-01 09:00:00', 'Europe/Madrid'),
                'disabled_at' => null,
            ],
        );
    }

    private function authUserExists(string $authUserId): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return true;
        }

        try {
            return DB::table('auth.users')->where('id', $authUserId)->exists();
        } catch (QueryException) {
            return false;
        }
    }

    private function seedCommercialContext(Client $client): void
    {
        ClientBranding::query()->updateOrCreate(
            ['client_id' => $client->id],
            [
                'color_primary' => '#244CFF',
                'color_secondary' => '#101828',
                'color_accent' => '#24C8DB',
                'font_family' => 'Inter',
            ],
        );

        ClientSubscription::query()->updateOrCreate(
            ['client_id' => $client->id],
            [
                'default_data_frequency' => 'daily',
                'default_retroactive_days' => 7,
                'default_max_posts_per_period' => 350,
                'competitor_analysis_enabled' => true,
                'ai_chatbot_enabled' => true,
                'ai_pattern_detection_enabled' => true,
                'client_presentations_enabled' => true,
                'monthly_message_limit' => 500,
                'monthly_price' => 1490,
                'price_basis' => 'Demo workspace for product development',
                'billing_cycle' => 'monthly',
                'contract_start' => '2026-06-01',
            ],
        );

        $planItems = [
            ['platform', 'Instagram monitoring', 1, 290],
            ['platform', 'TikTok monitoring', 1, 290],
            ['platform', 'X monitoring', 1, 190],
            ['competitor', 'Competitor tracking pack', 2, 150],
            ['chatbot', 'AI analyst chatbot', 1, 320],
            ['reports', 'Monthly executive report', 1, 250],
        ];

        foreach ($planItems as [$type, $name, $quantity, $monthlyPrice]) {
            ClientPlanItem::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'item_type' => $type,
                    'item_name' => $name,
                ],
                [
                    'quantity' => $quantity,
                    'monthly_price' => $monthlyPrice,
                    'notes' => 'Demo pricing component.',
                ],
            );
        }
    }

    /**
     * @param  array<string, Platform>  $platforms
     */
    private function seedClientPlatforms(Client $client, array $platforms): void
    {
        foreach (['instagram', 'tiktok', 'x'] as $code) {
            DB::table('client_platforms')->updateOrInsert(
                [
                    'client_id' => $client->id,
                    'platform_id' => $platforms[$code]->id,
                ],
                [
                    'enabled' => true,
                    'enabled_at' => Carbon::parse('2026-06-01 09:00:00', 'Europe/Madrid'),
                    'disabled_at' => null,
                ],
            );
        }
    }

    /**
     * @return array<string, Brand>
     */
    private function seedBrands(Client $client): array
    {
        $ownBrand = Brand::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'name' => 'Sibilare',
                'brand_type' => 'own_brand',
            ],
            [
                'color' => '#244CFF',
                'website_url' => 'https://sibilare.example',
                'is_active' => true,
            ],
        );

        $ownSubbrand = Brand::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'name' => 'Sibilare Insights',
                'brand_type' => 'own_subbrand',
            ],
            [
                'parent_brand_id' => $ownBrand->id,
                'color' => '#24C8DB',
                'website_url' => 'https://sibilare.example/insights',
                'is_active' => true,
            ],
        );

        $brandwatch = Brand::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'name' => 'Brandwatch',
                'brand_type' => 'competitor',
            ],
            [
                'color' => '#FF6B35',
                'website_url' => 'https://www.brandwatch.com',
                'is_active' => true,
            ],
        );

        $hootsuite = Brand::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'name' => 'Hootsuite',
                'brand_type' => 'competitor',
            ],
            [
                'color' => '#00A876',
                'website_url' => 'https://www.hootsuite.com',
                'is_active' => true,
            ],
        );

        return [
            'sibilare' => $ownBrand,
            'sibilare_insights' => $ownSubbrand,
            'brandwatch' => $brandwatch,
            'hootsuite' => $hootsuite,
        ];
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Platform>  $platforms
     */
    private function seedBrandAliases(array $brands, array $platforms): void
    {
        $aliases = [
            ['sibilare', 'instagram', 'handle', '@sibilare'],
            ['sibilare', 'tiktok', 'handle', '@sibilare'],
            ['sibilare', 'x', 'handle', '@sibilare_ai'],
            ['sibilare', 'instagram', 'hashtag', '#sibilare'],
            ['sibilare_insights', 'x', 'keyword', 'Sibilare Insights'],
            ['brandwatch', 'instagram', 'handle', '@brandwatch'],
            ['brandwatch', 'x', 'keyword', 'Brandwatch'],
            ['hootsuite', 'tiktok', 'keyword', 'Hootsuite'],
            ['hootsuite', 'x', 'handle', '@hootsuite'],
        ];

        foreach ($aliases as [$brandKey, $platformCode, $aliasType, $value]) {
            BrandAlias::query()->updateOrCreate(
                [
                    'brand_id' => $brands[$brandKey]->id,
                    'platform_id' => $platforms[$platformCode]->id,
                    'alias_type' => $aliasType,
                    'value' => $value,
                ],
                ['is_primary' => $aliasType === 'handle'],
            );
        }
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Platform>  $platforms
     */
    private function seedVolumeEstimates(Client $client, array $brands, array $platforms): void
    {
        $estimates = [
            ['sibilare', 'instagram', 420, 0.82, 'growth'],
            ['sibilare', 'tiktok', 260, 0.74, 'growth'],
            ['sibilare', 'x', 180, 0.69, 'starter'],
            ['brandwatch', 'instagram', 920, 0.78, 'competitive'],
            ['hootsuite', 'tiktok', 610, 0.72, 'competitive'],
        ];

        foreach ($estimates as [$brandKey, $platformCode, $mentions, $confidence, $tier]) {
            BrandPlatformVolumeEstimate::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'brand_id' => $brands[$brandKey]->id,
                    'platform_id' => $platforms[$platformCode]->id,
                ],
                [
                    'estimated_monthly_mentions' => $mentions,
                    'source' => 'manual',
                    'confidence' => $confidence,
                    'suggested_tier' => $tier,
                    'suggested_multiplier' => $mentions >= 600 ? 1.35 : 1,
                    'notes' => 'Seeded estimate for demo dashboards.',
                ],
            );
        }
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Platform>  $platforms
     */
    private function seedDataAvailability(Client $client, array $brands, array $platforms): void
    {
        foreach (['sibilare', 'brandwatch', 'hootsuite'] as $brandKey) {
            foreach (['instagram', 'tiktok', 'x'] as $platformCode) {
                ClientDataAvailability::query()->updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'brand_id' => $brands[$brandKey]->id,
                        'platform_id' => $platforms[$platformCode]->id,
                    ],
                    [
                        'data_starts_at' => Carbon::parse('2026-06-01 00:00:00', 'Europe/Madrid'),
                        'historical_backfill_available' => true,
                        'coverage_note' => 'Demo coverage seeded for local dashboard development.',
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, Platform>  $platforms
     * @return array<string, ApifyAgent>
     */
    private function seedApifyAgents(array $platforms): array
    {
        $definitions = [
            'instagram' => ['Instagram Public Profile Scraper', 'apify/instagram-profile-scraper', 0.035, 0.0012],
            'tiktok' => ['TikTok Search Scraper', 'clockworks/tiktok-scraper', 0.045, 0.0018],
            'x' => ['X Search Scraper', 'apify/twitter-search-scraper', 0.05, 0.002],
        ];

        $agents = [];

        foreach ($definitions as $code => [$name, $actorId, $costPerRun, $costPerItem]) {
            $agents[$code] = ApifyAgent::query()->updateOrCreate(
                [
                    'platform_id' => $platforms[$code]->id,
                    'actor_id' => $actorId,
                ],
                [
                    'name' => $name,
                    'is_primary' => true,
                    'priority' => 10,
                    'cost_per_run_estimate' => $costPerRun,
                    'cost_per_item_estimate' => $costPerItem,
                    'is_active' => true,
                    'last_used_at' => Carbon::parse('2026-06-28 12:00:00', 'Europe/Madrid'),
                ],
            );
        }

        return $agents;
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Platform>  $platforms
     * @return array<string, ExtractionConfig>
     */
    private function seedExtractionConfigs(Client $client, array $brands, array $platforms): array
    {
        $definitions = [
            'sibilare_instagram' => ['sibilare', 'instagram', '@sibilare OR #sibilare', 'daily', 7, 120, 'engagement_weighted', 18],
            'sibilare_tiktok' => ['sibilare', 'tiktok', 'sibilare social listening', 'daily', 7, 80, 'most_recent', 16],
            'brandwatch_instagram' => ['brandwatch', 'instagram', '@brandwatch OR Brandwatch', 'weekly', 14, 100, 'most_relevant', 14],
            'hootsuite_x' => ['hootsuite', 'x', 'Hootsuite OR @hootsuite', 'weekly', 14, 90, 'most_relevant', 12],
        ];

        $configs = [];

        foreach ($definitions as $key => [$brandKey, $platformCode, $query, $frequency, $days, $maxPosts, $strategy, $costLimit]) {
            $configs[$key] = ExtractionConfig::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'brand_id' => $brands[$brandKey]->id,
                    'platform_id' => $platforms[$platformCode]->id,
                    'search_query' => $query,
                ],
                [
                    'frequency' => $frequency,
                    'retroactive_days' => $days,
                    'max_posts_per_run' => $maxPosts,
                    'selection_strategy' => $strategy,
                    'cost_limit_per_run' => $costLimit,
                    'is_active' => true,
                ],
            );
        }

        return $configs;
    }

    /**
     * @param  array<string, ExtractionConfig>  $configs
     * @param  array<string, ApifyAgent>  $agents
     * @return array<string, ExtractionRun>
     */
    private function seedExtractionRuns(Client $client, array $configs, array $agents): array
    {
        $runs = [];

        foreach ($configs as $key => $config) {
            $scheduledFor = Carbon::parse('2026-06-28 08:00:00', 'Europe/Madrid');

            $job = ExtractionJob::query()->updateOrCreate(
                [
                    'extraction_config_id' => $config->id,
                    'client_id' => $client->id,
                    'scheduled_for' => $scheduledFor,
                ],
                [
                    'status' => 'completed',
                    'retry_count' => 0,
                    'max_retries' => 3,
                ],
            );

            $platformCode = (string) $config->platform->code;

            $runs[$key] = ExtractionRun::query()->updateOrCreate(
                [
                    'extraction_config_id' => $config->id,
                    'client_id' => $client->id,
                    'period_start' => Carbon::parse('2026-06-21 00:00:00', 'Europe/Madrid'),
                ],
                [
                    'extraction_job_id' => $job->id,
                    'brand_id' => $config->brand_id,
                    'platform_id' => $config->platform_id,
                    'agent_id' => $agents[$platformCode]->id ?? null,
                    'frequency_type' => $config->frequency,
                    'period_end' => Carbon::parse('2026-06-28 00:00:00', 'Europe/Madrid'),
                    'status' => 'success',
                    'input_payload' => [
                        'query' => $config->search_query,
                        'maxItems' => $config->max_posts_per_run,
                    ],
                    'result_summary' => [
                        'demo' => true,
                        'top_language' => 'es',
                        'selection_strategy' => $config->selection_strategy,
                    ],
                    'posts_requested' => $config->max_posts_per_run,
                    'posts_fetched' => 42,
                    'posts_stored' => 12,
                    'posts_discarded' => 30,
                    'cost_amount' => 3.85,
                    'currency' => 'EUR',
                    'started_at' => Carbon::parse('2026-06-28 08:00:00', 'Europe/Madrid'),
                    'finished_at' => Carbon::parse('2026-06-28 08:03:00', 'Europe/Madrid'),
                ],
            );
        }

        return $runs;
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Platform>  $platforms
     * @param  array<string, ExtractionRun>  $runs
     * @return array<string, Post>
     */
    private function seedPosts(Client $client, array $brands, array $platforms, array $runs): array
    {
        $definitions = [
            'ig-sibilare-001' => ['sibilare', 'instagram', 'sibilare_instagram', '@ana_growth', 'Ana Growth', 'Probando Sibilare para entender menciones de marca y competidores en una sola vista.', 'https://instagram.example/p/ig-sibilare-001', '2026-06-27 18:35:00', ['likes' => 142, 'comments' => 18, 'shares' => 7], '@sibilare', 'brand', true],
            'ig-sibilare-002' => ['sibilare', 'instagram', 'sibilare_instagram', '@martech_diary', 'Martech Diary', 'El dashboard de Sibilare ayuda a priorizar conversaciones relevantes antes del informe mensual.', 'https://instagram.example/p/ig-sibilare-002', '2026-06-26 11:20:00', ['likes' => 88, 'comments' => 9, 'shares' => 4], '#sibilare', 'alias', true],
            'tk-sibilare-001' => ['sibilare', 'tiktok', 'sibilare_tiktok', '@socialops', 'Social Ops', 'Comparativa rapida de herramientas de social listening para equipos pequenos.', 'https://tiktok.example/@socialops/video/001', '2026-06-25 20:10:00', ['likes' => 510, 'comments' => 44, 'shares' => 31], 'sibilare social listening', 'keyword', true],
            'ig-brandwatch-001' => ['brandwatch', 'instagram', 'brandwatch_instagram', '@insights_lab', 'Insights Lab', 'Brandwatch sigue siendo fuerte en enterprise, pero el setup inicial pesa para equipos pequenos.', 'https://instagram.example/p/ig-brandwatch-001', '2026-06-24 09:50:00', ['likes' => 216, 'comments' => 27, 'shares' => 11], 'Brandwatch', 'competitor', true],
            'x-hootsuite-001' => ['hootsuite', 'x', 'hootsuite_x', '@cmworld', 'CM World', 'Hootsuite es practico para programacion, aunque el listening avanzado pide otra capa.', 'https://x.example/cmworld/status/001', '2026-06-23 14:05:00', ['likes' => 73, 'reposts' => 12, 'replies' => 6], 'Hootsuite', 'competitor', true],
            'x-hootsuite-002' => ['hootsuite', 'x', 'hootsuite_x', '@random_post', 'Random Post', 'Mencion tangencial de Hootsuite sin valor analitico claro para el cliente.', 'https://x.example/random_post/status/002', '2026-06-22 16:40:00', ['likes' => 9, 'reposts' => 1, 'replies' => 0], '@hootsuite', 'alias', false],
        ];

        $posts = [];

        foreach ($definitions as $externalId => [$brandKey, $platformCode, $runKey, $authorHandle, $authorName, $content, $url, $postedAt, $metrics, $matchedQuery, $matchType, $isRelevant]) {
            $platformPost = PlatformPost::query()->updateOrCreate(
                [
                    'platform_id' => $platforms[$platformCode]->id,
                    'external_id' => $externalId,
                ],
                [
                    'author_handle' => $authorHandle,
                    'author_name' => $authorName,
                    'content_text' => $content,
                    'url' => $url,
                    'posted_at' => Carbon::parse($postedAt, 'Europe/Madrid'),
                    'language_code' => 'es',
                    'metrics' => $metrics,
                    'raw_payload' => [
                        'demo' => true,
                        'source' => 'SmlDemoSeeder',
                    ],
                ],
            );

            $posts[$externalId] = Post::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'platform_post_id' => $platformPost->id,
                    'brand_id' => $brands[$brandKey]->id,
                ],
                [
                    'extraction_run_id' => $runs[$runKey]->id,
                    'matched_query' => $matchedQuery,
                    'match_type' => $matchType,
                    'is_relevant_candidate' => $isRelevant,
                ],
            );
        }

        return $posts;
    }

    /**
     * @param  array<string, ExtractionRun>  $runs
     * @param  array<string, Post>  $posts
     */
    private function seedUsageLedger(Client $client, array $runs, array $posts): void
    {
        foreach ($runs as $run) {
            UsageLedger::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'usage_type' => 'apify_run',
                    'source_table' => 'extraction_runs',
                    'source_id' => $run->id,
                ],
                [
                    'brand_id' => $run->brand_id,
                    'platform_id' => $run->platform_id,
                    'quantity' => 1,
                    'unit' => 'run',
                    'cost_amount' => $run->cost_amount,
                    'currency' => 'EUR',
                    'occurred_at' => $run->finished_at,
                    'metadata' => [
                        'demo' => true,
                        'posts_stored' => $run->posts_stored,
                    ],
                ],
            );
        }

        foreach ($posts as $post) {
            UsageLedger::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'usage_type' => 'post_classification',
                    'source_table' => 'posts',
                    'source_id' => $post->id,
                ],
                [
                    'brand_id' => $post->brand_id,
                    'platform_id' => $post->platformPost->platform_id,
                    'quantity' => 1,
                    'unit' => 'post',
                    'cost_amount' => 0.0025,
                    'currency' => 'EUR',
                    'occurred_at' => Carbon::parse('2026-06-28 08:05:00', 'Europe/Madrid'),
                    'metadata' => [
                        'demo' => true,
                        'relevance_candidate' => $post->is_relevant_candidate,
                    ],
                ],
            );
        }

        UsageLedger::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'usage_type' => 'chatbot_message',
                'source_table' => null,
                'source_id' => null,
            ],
            [
                'quantity' => 12,
                'unit' => 'message',
                'cost_amount' => 0.84,
                'currency' => 'EUR',
                'occurred_at' => Carbon::parse('2026-06-29 10:15:00', 'Europe/Madrid'),
                'metadata' => [
                    'demo' => true,
                    'conversation' => 'weekly-insights-review',
                ],
            ],
        );
    }
}
