<?php

namespace Tests\Feature;

use App\Domain\Extraction\Actions\ClaimPendingExtractionJob;
use App\Domain\Extraction\Actions\FinalizeExtractionRun;
use App\Domain\Extraction\Actions\LaunchExtractionRun;
use App\Domain\Extraction\Actions\ReserveExtractionBudget;
use App\Domain\Extraction\Actions\ScheduleDueExtractions;
use App\Domain\Extraction\Actions\StoreNormalizedExtractionItems;
use App\Domain\Extraction\Exceptions\ExtractionBudgetExceeded;
use App\Jobs\ClaimAndLaunchExtraction;
use App\Jobs\FinalizeApifyExtraction;
use App\Models\ApifyAgent;
use App\Models\Brand;
use App\Models\Client;
use App\Models\ExtractionBatch;
use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;
use App\Models\ExtractionRun;
use App\Models\Platform;
use App\Models\PlatformPost;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
        Schema::dropIfExists('cost_budgets');
        Schema::dropIfExists('project_posts');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('platform_posts');
        Schema::dropIfExists('extraction_runs');
        Schema::dropIfExists('apify_agents');
        Schema::dropIfExists('extraction_batch_jobs');
        Schema::dropIfExists('extraction_batches');
        Schema::dropIfExists('extraction_jobs');
        Schema::dropIfExists('extraction_configs');
        Schema::dropIfExists('dashboards');
        Schema::dropIfExists('project_brands');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('client_platforms');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('metric_definitions');
        Schema::dropIfExists('client_branding');
        Schema::dropIfExists('client_users');
        Schema::dropIfExists('client_subscriptions');
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

        Schema::create('apify_agents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('platform_id');
            $table->text('name');
            $table->text('actor_id');
            $table->boolean('is_primary')->default(false);
            $table->integer('priority')->default(100);
            $table->decimal('cost_per_run_estimate', 12, 4)->nullable();
            $table->decimal('cost_per_item_estimate', 12, 6)->nullable();
            $table->text('billing_model')->default('per_item');
            $table->text('pricing_unit')->nullable();
            $table->json('pricing_details')->nullable();
            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->boolean('supports_webhook')->default(true);
            $table->json('actor_options')->nullable();
            $table->integer('max_items_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->nullable();
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

        Schema::create('client_subscriptions', function (Blueprint $table): void {
            $table->uuid('client_id')->primary();
            $table->text('default_data_frequency')->default('weekly');
            $table->integer('default_retroactive_days')->default(3);
            $table->integer('default_max_posts_per_period')->default(100);
            $table->timestamps();
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

        Schema::create('projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->text('name');
            $table->text('slug');
            $table->text('description')->nullable();
            $table->text('status')->default('active');
            $table->text('default_data_frequency')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'slug']);
        });

        Schema::create('project_brands', function (Blueprint $table): void {
            $table->uuid('project_id');
            $table->uuid('brand_id');
            $table->uuid('client_id');
            $table->timestamp('created_at')->nullable();
            $table->primary(['project_id', 'brand_id']);
        });

        Schema::create('dashboards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('project_id')->nullable();
        });

        Schema::create('extraction_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('brand_id');
            $table->uuid('platform_id');
            $table->text('search_query');
            $table->text('frequency')->nullable();
            $table->integer('retroactive_days')->default(3);
            $table->integer('max_posts_per_run');
            $table->text('selection_strategy')->default('most_relevant');
            $table->decimal('cost_limit_per_run', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('query_fingerprint')->nullable();
            $table->timestamps();
        });

        Schema::create('extraction_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('extraction_config_id');
            $table->uuid('client_id');
            $table->timestamp('scheduled_for');
            $table->text('frequency_type')->nullable();
            $table->integer('overlap_days')->default(3);
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('fetch_start')->nullable();
            $table->timestamp('fetch_end')->nullable();
            $table->decimal('reserved_cost_usd', 12, 4)->default(0);
            $table->text('status')->default('pending');
            $table->timestamp('locked_at')->nullable();
            $table->text('locked_by')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['extraction_config_id', 'period_start', 'period_end']);
        });

        Schema::create('extraction_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('requested_by_client_user_id')->nullable();
            $table->text('status')->default('queued');
            $table->integer('total_jobs')->default(0);
            $table->integer('pending_jobs')->default(0);
            $table->integer('active_jobs')->default(0);
            $table->integer('completed_jobs')->default(0);
            $table->integer('failed_jobs')->default(0);
            $table->integer('skipped_jobs')->default(0);
            $table->decimal('reserved_cost_usd', 12, 4)->default(0);
            $table->decimal('usage_cost_usd', 12, 6)->default(0);
            $table->decimal('billed_cost_usd', 12, 6)->default(0);
            $table->timestamp('launched_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('extraction_batch_jobs', function (Blueprint $table): void {
            $table->uuid('extraction_batch_id');
            $table->uuid('extraction_job_id');
            $table->uuid('client_id');
            $table->timestamp('created_at')->nullable();
            $table->primary(['extraction_batch_id', 'extraction_job_id']);
        });

        Schema::create('extraction_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('extraction_job_id')->nullable();
            $table->uuid('extraction_config_id')->nullable();
            $table->uuid('client_id');
            $table->uuid('brand_id');
            $table->uuid('platform_id');
            $table->uuid('agent_id')->nullable();
            $table->uuid('fallback_from_agent_id')->nullable();
            $table->text('fallback_reason')->nullable();
            $table->text('external_run_id')->nullable();
            $table->text('dataset_id')->nullable();
            $table->integer('attempt_number')->default(1);
            $table->text('frequency_type')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->text('status');
            $table->timestamp('fetch_start')->nullable();
            $table->timestamp('fetch_end')->nullable();
            $table->json('input_payload')->nullable();
            $table->json('result_summary')->nullable();
            $table->integer('posts_requested')->nullable();
            $table->integer('posts_fetched')->nullable();
            $table->integer('posts_stored')->nullable();
            $table->integer('posts_discarded')->nullable();
            $table->decimal('cost_amount', 12, 4)->nullable();
            $table->decimal('compute_units', 14, 6)->nullable();
            $table->decimal('usage_cost_usd', 12, 6)->nullable();
            $table->decimal('billed_cost_usd', 12, 6)->nullable();
            $table->json('charged_event_counts')->nullable();
            $table->json('pricing_snapshot')->nullable();
            $table->text('abort_reason')->nullable();
            $table->json('guardrails_hit')->nullable();
            $table->text('currency')->default('USD');
            $table->text('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('webhook_received_at')->nullable();
            $table->timestamp('finalization_started_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
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

        Schema::create('project_posts', function (Blueprint $table): void {
            $table->uuid('project_id');
            $table->uuid('post_id');
            $table->uuid('client_id');
            $table->uuid('extraction_run_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->primary(['project_id', 'post_id']);
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

        Schema::create('cost_budgets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->text('scope_type');
            $table->uuid('brand_id')->nullable();
            $table->uuid('platform_id')->nullable();
            $table->text('feature_code')->nullable();
            $table->text('period');
            $table->decimal('soft_limit_amount', 12, 4)->nullable();
            $table->decimal('hard_limit_amount', 12, 4)->nullable();
            $table->text('currency')->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
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

    public function test_projects_are_tenant_safe_and_drive_extraction_frequency(): void
    {
        DB::table('client_subscriptions')->insert([
            'client_id' => $this->client->id,
            'default_data_frequency' => 'weekly',
            'default_retroactive_days' => 3,
            'default_max_posts_per_period' => 100,
        ]);
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Colacao',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);

        $projectResponse = $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/projects", [
                'name' => 'Marketing',
                'default_data_frequency' => 'daily',
                'brand_ids' => [$brand->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'marketing')
            ->assertJsonPath('data.brands.0.id', $brand->id);
        $projectId = $projectResponse->json('data.id');

        $configResponse = $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/extraction-configs", [
                'project_id' => $projectId,
                'brand_id' => $brand->id,
                'platform_id' => $this->platform->id,
                'search_query' => 'Colacao',
                'frequency' => null,
                'max_posts_per_run' => 100,
            ])
            ->assertCreated()
            ->assertJsonPath('data.effective_frequency', 'daily')
            ->assertJsonPath('data.overlap_days', 3);

        $now = CarbonImmutable::parse('2026-07-02 10:00:00', 'Europe/Madrid');
        $scheduler = app(ScheduleDueExtractions::class);

        $this->assertSame(1, $scheduler->handle($now));
        $this->assertSame(0, $scheduler->handle($now));
        $this->assertDatabaseHas('extraction_jobs', [
            'extraction_config_id' => $configResponse->json('data.id'),
            'frequency_type' => 'daily',
            'overlap_days' => 3,
            'status' => 'pending',
        ]);

        $otherClient = Client::create([
            'name' => 'Other tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'default_locale' => 'es-ES',
            'timezone' => 'Europe/Madrid',
        ]);
        $foreignBrand = Brand::create([
            'client_id' => $otherClient->id,
            'name' => 'Foreign brand',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->patchJson("/api/v1/clients/{$this->client->id}/projects/{$projectId}", [
                'brand_ids' => [$foreignBrand->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('brand_ids.0');
    }

    public function test_apify_webhook_is_authenticated_and_idempotently_queues_finalization(): void
    {
        Config::set('services.apify.webhook_secret', 'webhook-test-secret');
        Queue::fake();
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Sibilare',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);
        $config = ExtractionConfig::create([
            'client_id' => $this->client->id,
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'search_query' => 'Sibilare',
            'frequency' => 'daily',
            'retroactive_days' => 3,
            'max_posts_per_run' => 100,
            'selection_strategy' => 'most_recent',
            'is_active' => true,
        ]);
        $job = ExtractionJob::create([
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'scheduled_for' => now(),
            'status' => 'waiting_provider',
        ]);
        $run = ExtractionRun::create([
            'extraction_job_id' => $job->id,
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'external_run_id' => 'apify-run-1',
            'status' => 'waiting_provider',
            'started_at' => now(),
        ]);
        $payload = [
            'eventType' => 'ACTOR.RUN.SUCCEEDED',
            'resource' => ['id' => 'apify-run-1', 'defaultDatasetId' => 'dataset-1'],
        ];

        $this->postJson('/api/v1/internal/apify/webhook', $payload)
            ->assertUnauthorized();

        $this->withHeader('X-Apify-Webhook-Secret', 'webhook-test-secret')
            ->postJson('/api/v1/internal/apify/webhook', $payload)
            ->assertAccepted()
            ->assertJsonPath('matched', true);
        $this->withHeader('X-Apify-Webhook-Secret', 'webhook-test-secret')
            ->postJson('/api/v1/internal/apify/webhook', $payload)
            ->assertAccepted();

        Queue::assertPushed(FinalizeApifyExtraction::class, 1);
        $this->assertSame('finalizing', $run->refresh()->status);
        $this->assertSame('dataset-1', $run->dataset_id);
    }

    public function test_manual_extraction_batches_launch_jobs_and_return_live_payload(): void
    {
        Config::set('services.apify.token', 'apify-test-token');
        Config::set('services.apify.base_url', 'https://api.apify.test/v2');
        Config::set('services.apify.webhook_secret', 'webhook-test-secret');
        Config::set('services.apify.webhook_url', 'https://platform.test/api/v1/internal/apify/webhook');
        Http::fake([
            'https://api.apify.test/v2/acts/*/runs*' => Http::response([
                'data' => [
                    'id' => 'apify-run-manual',
                    'defaultDatasetId' => 'dataset-manual',
                ],
            ]),
        ]);
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Colacao',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);
        $project = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Marketing',
            'slug' => 'marketing',
            'status' => 'active',
            'default_data_frequency' => 'daily',
        ]);
        $project->brands()->attach($brand->id, ['client_id' => $this->client->id, 'created_at' => now()]);
        $config = ExtractionConfig::create([
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'search_query' => 'Colacao',
            'frequency' => null,
            'retroactive_days' => 3,
            'max_posts_per_run' => 50,
            'selection_strategy' => 'most_recent',
            'is_active' => true,
        ]);
        ApifyAgent::create([
            'platform_id' => $this->platform->id,
            'name' => 'Instagram Public Profile Scraper',
            'actor_id' => 'apify/instagram-profile-scraper',
            'is_primary' => true,
            'priority' => 10,
            'cost_per_run_estimate' => 0.01,
            'cost_per_item_estimate' => 0.001,
            'billing_model' => 'per_event',
            'supports_webhook' => true,
            'is_active' => true,
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/extraction-batches", [
                'project_id' => $project->id,
                'config_ids' => [$config->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.summary.total_jobs', 1)
            ->assertJsonPath('data.summary.active_jobs', 1)
            ->assertJsonPath('data.summary.pending_jobs', 0)
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.progress_percent', 0)
            ->assertJsonPath('data.jobs.0.config.id', $config->id);

        $this->assertDatabaseHas('extraction_runs', [
            'extraction_job_id' => ExtractionJob::query()->value('id'),
            'external_run_id' => 'apify-run-manual',
            'dataset_id' => 'dataset-manual',
            'status' => 'waiting_provider',
        ]);
        $this->assertDatabaseHas('extraction_batches', [
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'total_jobs' => 1,
        ]);
    }

    public function test_extraction_workspace_endpoint_aggregates_projects_configs_and_batches(): void
    {
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Colacao',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);
        $project = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Marketing',
            'slug' => 'marketing',
            'status' => 'active',
            'default_data_frequency' => 'daily',
        ]);
        $project->brands()->attach($brand->id, ['client_id' => $this->client->id, 'created_at' => now()]);
        $config = ExtractionConfig::create([
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'search_query' => 'Colacao',
            'frequency' => 'daily',
            'retroactive_days' => 3,
            'max_posts_per_run' => 50,
            'selection_strategy' => 'most_recent',
            'is_active' => true,
        ]);
        $job = ExtractionJob::create([
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'scheduled_for' => now(),
            'frequency_type' => 'daily',
            'period_start' => now()->startOfDay(),
            'period_end' => now()->addDay()->startOfDay(),
            'fetch_start' => now()->subDays(3)->startOfDay(),
            'fetch_end' => now()->addDay()->startOfDay(),
            'reserved_cost_usd' => 0.1234,
            'status' => 'pending',
        ]);
        $batch = ExtractionBatch::create([
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'status' => 'queued',
            'launched_at' => now(),
        ]);
        $batch->jobs()->attach($job->id, ['client_id' => $this->client->id]);

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/extraction-workspace")
            ->assertOk()
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonCount(1, 'data.configs')
            ->assertJsonCount(1, 'data.batches')
            ->assertJsonPath('data.projects.0.id', $project->id)
            ->assertJsonPath('data.configs.0.id', $config->id)
            ->assertJsonPath('data.batches.0.id', $batch->id)
            ->assertJsonPath('data.batches.0.summary.total_jobs', 1)
            ->assertJsonPath('data.batches.0.summary.pending_jobs', 1)
            ->assertJsonPath('data.batches.0.summary.reserved_cost_usd', '0.1234');
    }

    public function test_manual_extraction_batch_show_rolls_up_worker_status_and_costs(): void
    {
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Operations',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);
        $project = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Ops',
            'slug' => 'ops',
            'status' => 'active',
        ]);
        $project->brands()->attach($brand->id, ['client_id' => $this->client->id, 'created_at' => now()]);
        $config = ExtractionConfig::create([
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'search_query' => 'Ops',
            'frequency' => 'daily',
            'retroactive_days' => 3,
            'max_posts_per_run' => 40,
            'selection_strategy' => 'most_recent',
            'is_active' => true,
        ]);
        $job = ExtractionJob::create([
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'scheduled_for' => now(),
            'frequency_type' => 'daily',
            'period_start' => now()->startOfDay(),
            'period_end' => now()->addDay()->startOfDay(),
            'fetch_start' => now()->subDays(3)->startOfDay(),
            'fetch_end' => now()->addDay()->startOfDay(),
            'reserved_cost_usd' => 0,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $batch = ExtractionBatch::create([
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'status' => 'queued',
            'launched_at' => now(),
        ]);
        $batch->jobs()->attach($job->id, ['client_id' => $this->client->id]);
        $run = ExtractionRun::create([
            'extraction_job_id' => $job->id,
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'status' => 'success',
            'attempt_number' => 1,
            'posts_requested' => 40,
            'posts_stored' => 28,
            'usage_cost_usd' => 0.011,
            'billed_cost_usd' => 0.017,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        ExtractionRun::create([
            'extraction_job_id' => $job->id,
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'status' => 'failed',
            'attempt_number' => 0,
            'posts_requested' => 40,
            'posts_stored' => 0,
            'usage_cost_usd' => 0,
            'billed_cost_usd' => 0,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/extraction-batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.summary.completed_jobs', 1)
            ->assertJsonPath('data.summary.billed_cost_usd', '0.017000')
            ->assertJsonPath('data.jobs.0.latest_run.id', $run->id)
            ->assertJsonPath('data.progress_percent', 100);
    }

    public function test_normalization_deduplicates_posts_and_keeps_project_specific_visibility(): void
    {
        $xPlatform = Platform::create(['code' => 'x', 'name' => 'X', 'is_active' => true]);
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Colacao',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);
        $marketing = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Marketing',
            'slug' => 'marketing',
            'status' => 'active',
        ]);
        $administration = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Administration',
            'slug' => 'administration',
            'status' => 'active',
        ]);
        $marketing->brands()->attach($brand->id, ['client_id' => $this->client->id, 'created_at' => now()]);
        $administration->brands()->attach($brand->id, ['client_id' => $this->client->id, 'created_at' => now()]);
        $config = ExtractionConfig::create([
            'client_id' => $this->client->id,
            'project_id' => $marketing->id,
            'brand_id' => $brand->id,
            'platform_id' => $xPlatform->id,
            'search_query' => 'Colacao',
            'frequency' => 'daily',
            'retroactive_days' => 3,
            'max_posts_per_run' => 100,
            'selection_strategy' => 'most_recent',
            'is_active' => true,
        ]);
        $run = ExtractionRun::create([
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'brand_id' => $brand->id,
            'platform_id' => $xPlatform->id,
            'status' => 'finalizing',
            'fetch_start' => '2026-06-28 00:00:00',
            'fetch_end' => '2026-07-02 00:00:00',
        ]);
        $item = [
            'id' => 'tweet-1',
            'text' => 'Colacao summer campaign',
            'createdAt' => '2026-07-01T10:00:00Z',
            'url' => 'https://x.example/status/tweet-1',
            'author' => ['userName' => 'customer'],
            'likeCount' => 10,
        ];

        $stats = app(StoreNormalizedExtractionItems::class)->handle($run, [$item, $item]);

        $this->assertSame(['fetched' => 2, 'stored' => 1, 'discarded' => 1], $stats);
        $this->assertDatabaseCount('platform_posts', 1);
        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseHas('project_posts', ['project_id' => $marketing->id]);
        $this->assertDatabaseMissing('project_posts', ['project_id' => $administration->id]);
    }

    public function test_budget_reservations_block_overspend_and_claims_do_not_duplicate_work(): void
    {
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Budget brand',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);
        $config = ExtractionConfig::create([
            'client_id' => $this->client->id,
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'search_query' => 'Budget brand',
            'frequency' => 'daily',
            'retroactive_days' => 3,
            'max_posts_per_run' => 100,
            'selection_strategy' => 'most_recent',
            'is_active' => true,
        ]);
        $firstJob = ExtractionJob::create([
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
        ]);
        $secondJob = ExtractionJob::create([
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'scheduled_for' => now(),
            'status' => 'pending',
        ]);
        DB::table('cost_budgets')->insert([
            'id' => (string) Str::uuid(),
            'client_id' => $this->client->id,
            'scope_type' => 'feature',
            'feature_code' => 'apify',
            'period' => 'monthly',
            'hard_limit_amount' => 1,
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $this->client->usageLedger()->create([
            'usage_type' => 'apify_run',
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'quantity' => 1,
            'unit' => 'run',
            'cost_amount' => 0.9,
            'currency' => 'USD',
            'occurred_at' => now(),
        ]);

        try {
            app(ReserveExtractionBudget::class)->handle($firstJob, $config, 0.2);
            $this->fail('The hard budget should reject the reservation.');
        } catch (ExtractionBudgetExceeded) {
            $this->assertSame('0.0000', $firstJob->refresh()->reserved_cost_usd);
        }

        DB::table('cost_budgets')->update(['hard_limit_amount' => 2]);
        $this->assertSame(0.2, app(ReserveExtractionBudget::class)->handle($firstJob, $config, 0.2));

        $firstJob->update(['status' => 'pending']);
        $claim = app(ClaimPendingExtractionJob::class);
        $claimedFirst = $claim->handle('worker-one');
        $claimedSecond = $claim->handle('worker-two');

        $this->assertNotNull($claimedFirst);
        $this->assertNotNull($claimedSecond);
        $this->assertNotSame($claimedFirst->id, $claimedSecond->id);
        $this->assertSame(0, ExtractionJob::query()->where('status', 'pending')->count());
    }

    public function test_failed_provider_run_records_billing_and_requeues_with_fallback(): void
    {
        Config::set('services.apify.token', 'apify-test-token');
        Config::set('services.apify.base_url', 'https://api.apify.test/v2');
        Queue::fake();
        Http::fake([
            'https://api.apify.test/v2/actor-runs/external-primary' => Http::response([
                'data' => [
                    'id' => 'external-primary',
                    'status' => 'FAILED',
                    'statusMessage' => 'Actor failed',
                    'usageTotalUsd' => 0.01,
                    'chargedEventCounts' => ['apify-actor-start' => 1],
                ],
            ]),
        ]);
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Fallback brand',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);
        $config = ExtractionConfig::create([
            'client_id' => $this->client->id,
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'search_query' => 'Fallback brand',
            'frequency' => 'daily',
            'retroactive_days' => 3,
            'max_posts_per_run' => 100,
            'selection_strategy' => 'most_recent',
            'is_active' => true,
        ]);
        $primary = ApifyAgent::create([
            'platform_id' => $this->platform->id,
            'name' => 'Primary',
            'actor_id' => 'owner/primary',
            'is_primary' => true,
            'priority' => 10,
            'cost_per_run_estimate' => 0.01,
            'cost_per_item_estimate' => 0.001,
            'billing_model' => 'per_event',
            'pricing_details' => ['event_prices' => ['apify-actor-start' => 0.01]],
            'is_active' => true,
        ]);
        ApifyAgent::create([
            'platform_id' => $this->platform->id,
            'name' => 'Fallback',
            'actor_id' => 'owner/fallback',
            'is_primary' => false,
            'priority' => 20,
            'cost_per_run_estimate' => 0.01,
            'cost_per_item_estimate' => 0.001,
            'billing_model' => 'per_event',
            'is_active' => true,
        ]);
        $job = ExtractionJob::create([
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'scheduled_for' => now(),
            'status' => 'waiting_provider',
            'reserved_cost_usd' => 0.11,
        ]);
        $run = ExtractionRun::create([
            'extraction_job_id' => $job->id,
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'brand_id' => $brand->id,
            'platform_id' => $this->platform->id,
            'agent_id' => $primary->id,
            'external_run_id' => 'external-primary',
            'attempt_number' => 1,
            'status' => 'waiting_provider',
            'started_at' => now(),
        ]);

        app(FinalizeExtractionRun::class)->handle($run->id);

        $this->assertSame('failed', $run->refresh()->status);
        $this->assertSame('0.010000', $run->billed_cost_usd);
        $this->assertSame('pending', $job->refresh()->status);
        $this->assertSame(1, $job->retry_count);
        $this->assertDatabaseHas('usage_ledger', [
            'source_id' => $run->id,
            'currency' => 'USD',
            'cost_amount' => 0.01,
        ]);
        Queue::assertPushed(ClaimAndLaunchExtraction::class, 1);
    }

    public function test_successful_provider_run_finalizes_dataset_posts_projects_and_costs(): void
    {
        Config::set('services.apify.token', 'apify-test-token');
        Config::set('services.apify.base_url', 'https://api.apify.test/v2');
        Http::fake([
            'https://api.apify.test/v2/actor-runs/external-success' => Http::response([
                'data' => [
                    'id' => 'external-success',
                    'status' => 'SUCCEEDED',
                    'defaultDatasetId' => 'dataset-success',
                    'usageTotalUsd' => 0.005,
                    'chargedEventCounts' => ['tweet' => 1],
                ],
            ]),
            'https://api.apify.test/v2/datasets/dataset-success/items*' => Http::response([[
                'id' => 'tweet-success',
                'text' => 'A customer mentions Colacao',
                'createdAt' => '2026-07-01T10:00:00Z',
                'url' => 'https://x.example/status/tweet-success',
                'author' => ['userName' => 'customer'],
                'likeCount' => 12,
            ]]),
        ]);
        $xPlatform = Platform::create(['code' => 'x', 'name' => 'X', 'is_active' => true]);
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Colacao',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);
        $project = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Marketing',
            'slug' => 'marketing',
            'status' => 'active',
        ]);
        $project->brands()->attach($brand->id, ['client_id' => $this->client->id, 'created_at' => now()]);
        $config = ExtractionConfig::create([
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'brand_id' => $brand->id,
            'platform_id' => $xPlatform->id,
            'search_query' => 'Colacao',
            'frequency' => 'daily',
            'retroactive_days' => 3,
            'max_posts_per_run' => 100,
            'selection_strategy' => 'most_recent',
            'is_active' => true,
        ]);
        $agent = ApifyAgent::create([
            'platform_id' => $xPlatform->id,
            'name' => 'X actor',
            'actor_id' => 'owner/x',
            'is_primary' => true,
            'priority' => 10,
            'cost_per_item_estimate' => 0.00015,
            'billing_model' => 'per_event',
            'pricing_details' => ['event_prices' => ['tweet' => 0.00015]],
            'is_active' => true,
        ]);
        $job = ExtractionJob::create([
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'scheduled_for' => now(),
            'status' => 'waiting_provider',
            'reserved_cost_usd' => 0.015,
        ]);
        $run = ExtractionRun::create([
            'extraction_job_id' => $job->id,
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'brand_id' => $brand->id,
            'platform_id' => $xPlatform->id,
            'agent_id' => $agent->id,
            'external_run_id' => 'external-success',
            'attempt_number' => 1,
            'status' => 'waiting_provider',
            'fetch_start' => '2026-06-28 00:00:00',
            'fetch_end' => '2026-07-02 00:00:00',
            'posts_requested' => 100,
            'started_at' => now(),
        ]);

        app(FinalizeExtractionRun::class)->handle($run->id);

        $this->assertSame('success', $run->refresh()->status);
        $this->assertSame(1, $run->posts_stored);
        $this->assertSame('completed', $job->refresh()->status);
        $this->assertSame('0.0000', $job->reserved_cost_usd);
        $this->assertDatabaseHas('project_posts', ['project_id' => $project->id]);
        $this->assertDatabaseHas('usage_ledger', ['source_id' => $run->id, 'cost_amount' => 0.00015]);
    }

    public function test_launcher_starts_an_async_run_with_an_authenticated_webhook(): void
    {
        Config::set('services.apify.token', 'apify-test-token');
        Config::set('services.apify.base_url', 'https://api.apify.test/v2');
        Config::set('services.apify.webhook_secret', 'webhook-secret');
        Config::set('services.apify.webhook_url', 'https://sml.example/api/v1/internal/apify/webhook');
        Http::fake([
            'https://api.apify.test/v2/acts/owner~x-actor/runs*' => Http::response([
                'data' => ['id' => 'external-launched', 'defaultDatasetId' => 'dataset-launched'],
            ]),
        ]);
        $xPlatform = Platform::create(['code' => 'x', 'name' => 'X', 'is_active' => true]);
        DB::table('client_platforms')->insert([
            'client_id' => $this->client->id,
            'platform_id' => $xPlatform->id,
            'enabled' => true,
        ]);
        $brand = Brand::create([
            'client_id' => $this->client->id,
            'name' => 'Launch brand',
            'brand_type' => 'own_brand',
            'is_active' => true,
        ]);
        $config = ExtractionConfig::create([
            'client_id' => $this->client->id,
            'brand_id' => $brand->id,
            'platform_id' => $xPlatform->id,
            'search_query' => 'Launch brand',
            'frequency' => 'daily',
            'retroactive_days' => 3,
            'max_posts_per_run' => 25,
            'selection_strategy' => 'most_recent',
            'is_active' => true,
        ]);
        ApifyAgent::create([
            'platform_id' => $xPlatform->id,
            'name' => 'X actor',
            'actor_id' => 'owner/x-actor',
            'is_primary' => true,
            'priority' => 10,
            'cost_per_run_estimate' => 0.01,
            'cost_per_item_estimate' => 0.001,
            'billing_model' => 'per_event',
            'supports_webhook' => true,
            'max_items_limit' => 100,
            'is_active' => true,
        ]);
        $job = ExtractionJob::create([
            'extraction_config_id' => $config->id,
            'client_id' => $this->client->id,
            'scheduled_for' => now(),
            'frequency_type' => 'daily',
            'period_start' => '2026-07-01 00:00:00',
            'period_end' => '2026-07-02 00:00:00',
            'fetch_start' => '2026-06-28 00:00:00',
            'fetch_end' => '2026-07-02 00:00:00',
            'status' => 'locked',
        ]);

        $run = app(LaunchExtractionRun::class)->handle($job);

        $this->assertNotNull($run);
        $this->assertSame('external-launched', $run->external_run_id);
        $this->assertSame('waiting_provider', $job->refresh()->status);
        $this->assertSame('0.0350', $job->reserved_cost_usd);
        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $webhooks = json_decode(base64_decode((string) ($query['webhooks'] ?? ''), true) ?: '', true);

            return str_contains($request->url(), '/acts/owner~x-actor/runs')
                && $request['maxItems'] === 25
                && data_get($webhooks, '0.requestUrl') === 'https://sml.example/api/v1/internal/apify/webhook'
                && json_decode((string) data_get($webhooks, '0.headersTemplate'), true)['X-Apify-Webhook-Secret'] === 'webhook-secret';
        });
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
