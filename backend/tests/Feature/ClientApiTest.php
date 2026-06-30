<?php

namespace Tests\Feature;

use App\Models\Client;
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
