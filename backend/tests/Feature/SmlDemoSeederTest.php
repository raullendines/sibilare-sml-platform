<?php

namespace Tests\Feature;

use Database\Seeders\SmlDemoSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmlDemoSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.supabase.demo_auth_user_id');

        $this->createDemoSchema();
    }

    public function test_demo_seeder_creates_react_ready_demo_data_without_duplicates(): void
    {
        $this->artisan('db:seed', ['--class' => SmlDemoSeeder::class])
            ->assertSuccessful();

        $this->assertDatabaseHas('clients', [
            'slug' => 'sibilare-demo',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('brands', [
            'name' => 'Sibilare',
            'brand_type' => 'own_brand',
        ]);
        $this->assertDatabaseHas('brands', [
            'name' => 'Brandwatch',
            'brand_type' => 'competitor',
        ]);
        $this->assertSame(5, DB::table('platforms')->count());
        $this->assertSame(3, DB::table('client_platforms')->count());
        $this->assertSame(4, DB::table('brands')->count());
        $this->assertSame(4, DB::table('extraction_configs')->count());
        $this->assertSame(6, DB::table('posts')->count());
        $this->assertSame(11, DB::table('usage_ledger')->count());

        $firstCounts = $this->demoCounts();

        $this->artisan('db:seed', ['--class' => SmlDemoSeeder::class])
            ->assertSuccessful();

        $this->assertSame($firstCounts, $this->demoCounts());
    }

    public function test_demo_seeder_links_a_configured_supabase_user(): void
    {
        $authUserId = '11111111-1111-4111-8111-111111111111';
        Config::set('services.supabase.demo_auth_user_id', $authUserId);

        $this->artisan('db:seed', ['--class' => SmlDemoSeeder::class])
            ->assertSuccessful();

        $this->assertDatabaseHas('client_users', [
            'auth_user_id' => $authUserId,
            'role' => 'owner',
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function demoCounts(): array
    {
        return [
            'clients' => DB::table('clients')->count(),
            'client_platforms' => DB::table('client_platforms')->count(),
            'brands' => DB::table('brands')->count(),
            'brand_aliases' => DB::table('brand_aliases')->count(),
            'extraction_configs' => DB::table('extraction_configs')->count(),
            'extraction_runs' => DB::table('extraction_runs')->count(),
            'platform_posts' => DB::table('platform_posts')->count(),
            'posts' => DB::table('posts')->count(),
            'usage_ledger' => DB::table('usage_ledger')->count(),
        ];
    }

    private function createDemoSchema(): void
    {
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

        Schema::create('client_subscriptions', function (Blueprint $table): void {
            $table->uuid('client_id')->primary();
            $table->text('default_data_frequency');
            $table->integer('default_retroactive_days');
            $table->integer('default_max_posts_per_period');
            $table->boolean('competitor_analysis_enabled');
            $table->boolean('ai_chatbot_enabled');
            $table->boolean('ai_pattern_detection_enabled');
            $table->boolean('client_presentations_enabled');
            $table->integer('monthly_message_limit')->nullable();
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->text('price_basis')->nullable();
            $table->text('billing_cycle');
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('client_plan_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->text('item_type');
            $table->text('item_name');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('multiplier', 12, 4)->default(1);
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('platforms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('code')->unique();
            $table->text('name');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('client_platforms', function (Blueprint $table): void {
            $table->uuid('client_id');
            $table->uuid('platform_id');
            $table->boolean('enabled')->default(true);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->primary(['client_id', 'platform_id']);
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

        Schema::create('brand_aliases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('brand_id');
            $table->uuid('platform_id')->nullable();
            $table->text('alias_type');
            $table->text('value');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('brand_platform_volume_estimates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('brand_id');
            $table->uuid('platform_id');
            $table->integer('estimated_monthly_mentions');
            $table->text('source');
            $table->decimal('confidence', 4, 3)->nullable();
            $table->text('suggested_tier')->nullable();
            $table->decimal('suggested_multiplier', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('client_data_availability', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('brand_id');
            $table->uuid('platform_id');
            $table->timestamp('data_starts_at')->nullable();
            $table->boolean('historical_backfill_available')->default(false);
            $table->text('coverage_note')->nullable();
            $table->timestamp('created_at')->nullable();
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
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('extraction_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('brand_id');
            $table->uuid('platform_id');
            $table->text('search_query');
            $table->text('frequency')->nullable();
            $table->integer('retroactive_days');
            $table->integer('max_posts_per_run');
            $table->text('selection_strategy');
            $table->decimal('cost_limit_per_run', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('extraction_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('extraction_config_id');
            $table->uuid('client_id');
            $table->timestamp('scheduled_for');
            $table->text('status');
            $table->timestamp('locked_at')->nullable();
            $table->text('locked_by')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('extraction_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('extraction_job_id')->nullable();
            $table->uuid('extraction_config_id');
            $table->uuid('client_id');
            $table->uuid('brand_id');
            $table->uuid('platform_id');
            $table->uuid('agent_id')->nullable();
            $table->uuid('fallback_from_agent_id')->nullable();
            $table->text('fallback_reason')->nullable();
            $table->text('frequency_type')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->text('status');
            $table->json('input_payload')->nullable();
            $table->json('result_summary')->nullable();
            $table->integer('posts_requested')->nullable();
            $table->integer('posts_fetched')->nullable();
            $table->integer('posts_stored')->nullable();
            $table->integer('posts_discarded')->nullable();
            $table->decimal('cost_amount', 12, 4)->nullable();
            $table->text('currency')->default('EUR');
            $table->text('error_code')->nullable();
            $table->text('error_message')->nullable();
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
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
        });
    }
}
