<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Client;
use App\Models\Platform;
use App\Models\PlatformPost;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreClientApiTest extends TestCase
{
    private string $authUserId = '11111111-1111-4111-8111-111111111111';

    protected Client $client;

    protected Platform $platform;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.supabase.url', 'https://example.supabase.co');
        Config::set('services.supabase.publishable_key', 'publishable-test-key');

        Http::fake([
            'https://example.supabase.co/auth/v1/user' => Http::response([
                'id' => $this->authUserId,
                'email' => 'demo@sibilare.test',
            ], 200),
        ]);

        Schema::dropIfExists('usage_ledger');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('platform_posts');
        Schema::dropIfExists('extraction_runs');
        Schema::dropIfExists('extraction_configs');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('client_platforms');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('metric_definitions');
        Schema::dropIfExists('client_branding');
        Schema::dropIfExists('client_users');
        Schema::dropIfExists('clients');

        Schema::create('clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('name');
            $table->text('slug')->unique();
            $table->text('status')->default('onboarding');
            $table->text('industry')->nullable();
            $table->text('default_locale')->default('es-ES');
            $table->text('timezone')->default('Europe/Madrid');
            $table->timestamps();
        });

        Schema::create('platforms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('code')->unique();
            $table->text('name');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('metric_definitions', function (Blueprint $table): void {
            $table->text('code')->primary();
            $table->text('name');
            $table->text('description')->nullable();
            $table->text('source_domain');
            $table->text('value_type');
            $table->text('default_aggregation');
            $table->text('default_visualization_type');
            $table->json('config_schema');
            $table->boolean('is_active');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('client_platforms', function (Blueprint $table): void {
            $table->uuid('client_id');
            $table->uuid('platform_id');
            $table->boolean('enabled')->default(true);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->primary(['client_id', 'platform_id']);
        });

        Schema::create('client_branding', function (Blueprint $table): void {
            $table->uuid('client_id')->primary();
            $table->text('logo_url')->nullable();
            $table->text('logo_dark_url')->nullable();
            $table->text('favicon_url')->nullable();
            $table->text('color_primary')->nullable();
            $table->text('color_secondary')->nullable();
            $table->text('color_accent')->nullable();
            $table->text('font_family')->nullable();
            $table->text('custom_css')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('client_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('auth_user_id');
            $table->text('role');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('parent_brand_id')->nullable();
            $table->text('name');
            $table->text('brand_type');
            $table->text('logo_url')->nullable();
            $table->text('color')->nullable();
            $table->text('website_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('extraction_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('brand_id');
            $table->uuid('platform_id');
            $table->text('search_query');
            $table->text('frequency')->nullable();
            $table->integer('retroactive_days')->default(3);
            $table->integer('max_posts_per_run');
            $table->text('selection_strategy')->default('most_relevant');
            $table->decimal('cost_limit_per_run', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('extraction_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('brand_id');
            $table->uuid('platform_id');
            $table->text('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('platform_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('platform_id');
            $table->text('external_id');
            $table->text('author_handle')->nullable();
            $table->text('author_name')->nullable();
            $table->text('content_text')->nullable();
            $table->text('url')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('language_code')->nullable();
            $table->json('media_urls')->nullable();
            $table->json('metrics')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('brand_id');
            $table->uuid('platform_post_id');
            $table->uuid('extraction_run_id')->nullable();
            $table->text('matched_query')->nullable();
            $table->text('match_type');
            $table->boolean('is_relevant_candidate')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('usage_ledger', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->text('usage_type');
            $table->text('source_table')->nullable();
            $table->uuid('source_id')->nullable();
            $table->uuid('brand_id')->nullable();
            $table->uuid('platform_id')->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->text('unit');
            $table->decimal('cost_amount', 12, 4)->nullable();
            $table->text('currency')->default('EUR');
            $table->timestamp('occurred_at')->nullable();
            $table->json('metadata')->nullable();
        });

        $this->client = Client::create([
            'name' => 'Sibilare Demo',
            'slug' => 'sibilare-demo',
            'status' => 'active',
            'default_locale' => 'es-ES',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->client->users()->create([
            'auth_user_id' => $this->authUserId,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        $this->platform = Platform::create([
            'code' => 'instagram',
            'name' => 'Instagram',
            'is_active' => true,
        ]);

        DB::table('client_platforms')->insert([
            'client_id' => $this->client->id,
            'platform_id' => $this->platform->id,
            'enabled' => true,
        ]);

        foreach ([
            'mentions.total',
            'mentions.relevant',
            'mentions.timeline',
            'mentions.by_platform',
            'mentions.by_brand',
            'mentions.latest',
            'brands.total',
            'competitors.total',
            'usage.cost',
            'extractions.success_rate',
        ] as $metricCode) {
            DB::table('metric_definitions')->insert([
                'code' => $metricCode,
                'name' => $metricCode,
                'source_domain' => str_starts_with($metricCode, 'usage.') ? 'usage' : 'posts',
                'value_type' => 'number',
                'default_aggregation' => 'count',
                'default_visualization_type' => 'kpi',
                'config_schema' => '{}',
                'is_active' => true,
            ]);
        }
    }

    public function test_react_can_read_platform_catalog(): void
    {
        $this
            ->withToken('valid-supabase-token')
            ->getJson('/api/v1/platforms')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'instagram');

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/platforms")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'instagram');
    }

    public function test_react_preflight_requests_are_allowed(): void
    {
        $this
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Access-Control-Request-Method' => 'GET',
            ])
            ->options('/api/v1/platforms')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_react_can_manage_client_brands(): void
    {
        $response = $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/brands", [
                'name' => 'Sibilare',
                'brand_type' => 'own_brand',
                'color' => '#2f6fed',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sibilare')
            ->assertJsonPath('data.client_id', $this->client->id);

        $brandId = $response->json('data.id');

        $this
            ->withToken('valid-supabase-token')
            ->patchJson("/api/v1/clients/{$this->client->id}/brands/{$brandId}", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_react_can_manage_extraction_configs(): void
    {
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Sibilare',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/extraction-configs", [
                'brand_id' => $brand->id,
                'platform_id' => $this->platform->id,
                'search_query' => '@sibilare',
                'frequency' => 'daily',
                'max_posts_per_run' => 100,
            ])
            ->assertCreated()
            ->assertJsonPath('data.search_query', '@sibilare')
            ->assertJsonPath('data.selection_strategy', 'most_relevant');
    }

    public function test_react_can_read_posts_usage_and_overview(): void
    {
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Sibilare',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);

        $platformPost = PlatformPost::create([
            'platform_id' => $this->platform->id,
            'external_id' => 'ig-1',
            'author_handle' => 'sibilare',
            'content_text' => 'Demo post',
            'metrics' => ['likes' => 10],
            'raw_payload' => ['source' => 'test'],
        ]);

        $post = $this->client->posts()->create([
            'brand_id' => $brand->id,
            'platform_post_id' => $platformPost->id,
            'match_type' => 'brand',
            'is_relevant_candidate' => true,
        ]);

        $this->client->usageLedger()->create([
            'usage_type' => 'apify_run',
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'quantity' => 1,
            'unit' => 'run',
            'cost_amount' => 0.25,
            'currency' => 'EUR',
            'metadata' => ['actor' => 'demo'],
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.platform_post.content_text', 'Demo post');

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/usage-ledger")
            ->assertOk()
            ->assertJsonPath('data.0.usage_type', 'apify_run');

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/overview")
            ->assertOk()
            ->assertJsonPath('data.counts.posts', 1)
            ->assertJsonPath('data.counts.usage_entries', 1);
    }

    public function test_react_can_filter_posts_without_crossing_client_boundaries(): void
    {
        $ownBrand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Sibilare',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);
        $competitor = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Competitor',
            'brand_type' => 'competitor',
            'is_active' => true,
        ]);

        $matchingPost = PlatformPost::create([
            'platform_id' => $this->platform->id,
            'external_id' => 'matching-post',
            'content_text' => 'The campaign comparison is useful',
            'posted_at' => '2026-06-15 10:00:00',
        ]);
        $irrelevantPost = PlatformPost::create([
            'platform_id' => $this->platform->id,
            'external_id' => 'irrelevant-post',
            'content_text' => 'Campaign mention without value',
            'posted_at' => '2026-06-16 10:00:00',
        ]);
        $ownPost = PlatformPost::create([
            'platform_id' => $this->platform->id,
            'external_id' => 'own-post',
            'content_text' => 'The campaign belongs to our brand',
            'posted_at' => '2026-06-15 10:00:00',
        ]);

        $matchingClientPost = $this->client->posts()->create([
            'brand_id' => $competitor->id,
            'platform_post_id' => $matchingPost->id,
            'match_type' => 'competitor',
            'is_relevant_candidate' => true,
        ]);
        $this->client->posts()->create([
            'brand_id' => $competitor->id,
            'platform_post_id' => $irrelevantPost->id,
            'match_type' => 'competitor',
            'is_relevant_candidate' => false,
        ]);
        $this->client->posts()->create([
            'brand_id' => $ownBrand->id,
            'platform_post_id' => $ownPost->id,
            'match_type' => 'brand',
            'is_relevant_candidate' => true,
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/posts?platform_id={$this->platform->id}&date_from=2026-06-01&date_to=2026-06-30&search=campaign&brand_type=competitor&relevance=true")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingClientPost->id);

        $otherClient = Client::create([
            'name' => 'Other Client',
            'slug' => 'post-filter-other-client',
            'status' => 'active',
            'default_locale' => 'es-ES',
            'timezone' => 'Europe/Madrid',
        ]);
        $foreignBrand = Brand::create([
            'client_id' => $otherClient->id,
            'name' => 'Foreign brand',
            'brand_type' => 'competitor',
            'is_active' => true,
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/posts?brand_id={$foreignBrand->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('brand_id');
    }

    public function test_react_can_query_approved_dashboard_metrics_in_one_batch(): void
    {
        $ownBrand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Sibilare',
            'brand_type' => 'own_brand',
            'color' => '#111111',
            'is_active' => true,
        ]);
        $competitor = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Competitor',
            'brand_type' => 'competitor',
            'color' => '#999999',
            'is_active' => true,
        ]);

        $this->createMetricPost($ownBrand, 'current-own', '2026-06-10 10:00:00', true);
        $this->createMetricPost($competitor, 'current-competitor', '2026-06-11 10:00:00', false);
        $this->createMetricPost($ownBrand, 'previous-own', '2026-05-20 10:00:00', true);

        $this->client->usageLedger()->create([
            'usage_type' => 'apify_run',
            'brand_id' => $ownBrand->id,
            'platform_id' => $this->platform->id,
            'quantity' => 1,
            'unit' => 'run',
            'cost_amount' => 0.35,
            'currency' => 'EUR',
            'occurred_at' => '2026-06-12 10:00:00',
            'metadata' => [],
        ]);
        $this->client->usageLedger()->create([
            'usage_type' => 'apify_run',
            'brand_id' => $ownBrand->id,
            'platform_id' => $this->platform->id,
            'quantity' => 1,
            'unit' => 'run',
            'cost_amount' => 0.15,
            'currency' => 'EUR',
            'occurred_at' => '2026-05-20 10:00:00',
            'metadata' => [],
        ]);

        foreach (['success', 'failed'] as $status) {
            DB::table('extraction_runs')->insert([
                'id' => (string) Str::uuid(),
                'client_id' => $this->client->id,
                'brand_id' => $ownBrand->id,
                'platform_id' => $this->platform->id,
                'status' => $status,
                'started_at' => '2026-06-15 10:00:00',
                'created_at' => '2026-06-15 10:00:00',
            ]);
        }

        $queries = array_map(
            fn (string $metricCode): array => [
                'key' => $metricCode,
                'metric_code' => $metricCode,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'interval' => 'day',
                'limit' => 10,
            ],
            [
                'mentions.total',
                'mentions.relevant',
                'mentions.timeline',
                'mentions.by_platform',
                'mentions.by_brand',
                'mentions.latest',
                'brands.total',
                'competitors.total',
                'usage.cost',
                'extractions.success_rate',
            ],
        );

        $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/metrics/query", ['queries' => $queries])
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.metric_code', 'mentions.total')
            ->assertJsonPath('data.0.value', 2)
            ->assertJsonPath('data.0.comparison.previous_value', 1)
            ->assertJsonPath('data.0.comparison.change_percent', 100)
            ->assertJsonPath('data.1.value', 1)
            ->assertJsonCount(2, 'data.2.points')
            ->assertJsonPath('data.3.points.0.label', 'Instagram')
            ->assertJsonCount(2, 'data.4.points')
            ->assertJsonCount(2, 'data.5.items')
            ->assertJsonPath('data.5.items.0.platform_post.external_id', 'current-competitor')
            ->assertJsonPath('data.6.value', 2)
            ->assertJsonPath('data.7.value', 1)
            ->assertJsonPath('data.8.value', 0.35)
            ->assertJsonPath('data.9.value', 50);
    }

    public function test_metric_queries_reject_brand_filters_from_another_client(): void
    {
        $otherClient = Client::create([
            'name' => 'Metric Other Client',
            'slug' => 'metric-other-client',
            'status' => 'active',
            'default_locale' => 'es-ES',
            'timezone' => 'Europe/Madrid',
        ]);
        $foreignBrand = Brand::create([
            'client_id' => $otherClient->id,
            'name' => 'Foreign metric brand',
            'brand_type' => 'competitor',
            'is_active' => true,
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/metrics/query", [
                'queries' => [[
                    'key' => 'total',
                    'metric_code' => 'mentions.total',
                    'brand_ids' => [$foreignBrand->id],
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('queries.0.brand_ids.0');
    }

    public function test_client_routes_reject_users_without_membership(): void
    {
        $otherClient = Client::create([
            'name' => 'Other Client',
            'slug' => 'other-client',
            'status' => 'active',
            'default_locale' => 'es-ES',
            'timezone' => 'Europe/Madrid',
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$otherClient->id}/overview")
            ->assertForbidden();
    }

    private function createMetricPost(Brand $brand, string $externalId, string $postedAt, bool $relevant): void
    {
        $platformPost = PlatformPost::create([
            'platform_id' => $this->platform->id,
            'external_id' => $externalId,
            'author_handle' => '@metric-test',
            'content_text' => "Metric post {$externalId}",
            'posted_at' => $postedAt,
        ]);

        $this->client->posts()->create([
            'brand_id' => $brand->id,
            'platform_post_id' => $platformPost->id,
            'match_type' => $brand->brand_type === 'competitor' ? 'competitor' : 'brand',
            'is_relevant_candidate' => $relevant,
        ]);
    }
}
