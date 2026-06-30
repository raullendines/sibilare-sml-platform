<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Client;
use App\Models\Platform;
use App\Models\PlatformPost;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoreClientApiTest extends TestCase
{
    protected Client $client;

    protected Platform $platform;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.internal_api.token', 'test-token');

        Schema::dropIfExists('usage_ledger');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('platform_posts');
        Schema::dropIfExists('extraction_configs');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('client_branding');
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
            $table->timestamp('created_at')->nullable();
        });

        $this->client = Client::create([
            'name' => 'Sibilare Demo',
            'slug' => 'sibilare-demo',
            'status' => 'active',
            'default_locale' => 'es-ES',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->platform = Platform::create([
            'code' => 'instagram',
            'name' => 'Instagram',
            'is_active' => true,
        ]);
    }

    public function test_react_can_read_platform_catalog(): void
    {
        $this
            ->withToken('test-token')
            ->getJson('/api/v1/platforms')
            ->assertOk()
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
            ->withToken('test-token')
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
            ->withToken('test-token')
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
            ->withToken('test-token')
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
            ->withToken('test-token')
            ->getJson("/api/v1/clients/{$this->client->id}/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.platform_post.content_text', 'Demo post');

        $this
            ->withToken('test-token')
            ->getJson("/api/v1/clients/{$this->client->id}/usage-ledger")
            ->assertOk()
            ->assertJsonPath('data.0.usage_type', 'apify_run');

        $this
            ->withToken('test-token')
            ->getJson("/api/v1/clients/{$this->client->id}/overview")
            ->assertOk()
            ->assertJsonPath('data.counts.posts', 1)
            ->assertJsonPath('data.counts.usage_entries', 1);
    }
}
