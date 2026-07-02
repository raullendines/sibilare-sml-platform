<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPlatform;
use App\Models\Platform;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    private string $authUserId = '11111111-1111-4111-8111-111111111111';

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

        Schema::dropIfExists('client_platforms');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('client_subscriptions');
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

        Schema::create('client_subscriptions', function (Blueprint $table): void {
            $table->uuid('client_id')->primary();
            $table->text('default_data_frequency')->default('weekly');
            $table->integer('default_retroactive_days')->default(3);
            $table->integer('default_max_posts_per_period')->default(100);
            $table->boolean('competitor_analysis_enabled')->default(true);
            $table->boolean('ai_chatbot_enabled')->default(false);
            $table->boolean('ai_pattern_detection_enabled')->default(false);
            $table->boolean('client_presentations_enabled')->default(false);
            $table->integer('monthly_message_limit')->nullable();
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->text('price_basis')->nullable();
            $table->text('billing_cycle')->default('monthly');
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->timestamp('updated_at')->nullable();
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

        Platform::create(['code' => 'instagram', 'name' => 'Instagram', 'is_active' => true]);
        Platform::create(['code' => 'x', 'name' => 'X', 'is_active' => true]);
    }

    public function test_client_routes_require_supabase_bearer_token(): void
    {
        $this->getJson('/api/v1/clients')
            ->assertUnauthorized();
    }

    public function test_can_create_and_show_client(): void
    {
        $createResponse = $this
            ->withToken('valid-supabase-token')
            ->postJson('/api/v1/clients', [
                'name' => 'Sibilare Demo',
                'industry' => 'Social Media Listening',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sibilare Demo')
            ->assertJsonPath('data.slug', 'sibilare-demo')
            ->assertJsonPath('data.status', 'onboarding');

        $clientId = $createResponse->json('data.id');

        $this->assertDatabaseHas('client_users', [
            'client_id' => $clientId,
            'auth_user_id' => $this->authUserId,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('client_subscriptions', [
            'client_id' => $clientId,
            'default_data_frequency' => 'weekly',
            'default_retroactive_days' => 3,
            'default_max_posts_per_period' => 100,
        ]);
        $this->assertSame(2, ClientPlatform::query()->where('client_id', $clientId)->count());

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$clientId}")
            ->assertOk()
            ->assertJsonPath('data.id', $clientId)
            ->assertJsonPath('data.default_locale', 'es-ES')
            ->assertJsonPath('data.timezone', 'Europe/Madrid');
    }

    public function test_can_update_client_status(): void
    {
        $client = Client::create([
            'name' => 'Client In Onboarding',
            'slug' => 'client-in-onboarding',
            'status' => 'onboarding',
            'default_locale' => 'es-ES',
            'timezone' => 'Europe/Madrid',
        ]);

        $client->users()->create([
            'auth_user_id' => $this->authUserId,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->patchJson("/api/v1/clients/{$client->id}", [
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'status' => 'active',
        ]);
    }
}
