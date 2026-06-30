<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.internal_api.token', 'test-token');

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
    }

    public function test_client_routes_require_internal_token(): void
    {
        $this->getJson('/api/v1/clients')
            ->assertUnauthorized();
    }

    public function test_can_create_and_show_client(): void
    {
        $createResponse = $this
            ->withToken('test-token')
            ->postJson('/api/v1/clients', [
                'name' => 'Sibilare Demo',
                'industry' => 'Social Media Listening',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sibilare Demo')
            ->assertJsonPath('data.slug', 'sibilare-demo')
            ->assertJsonPath('data.status', 'onboarding');

        $clientId = $createResponse->json('data.id');

        $this
            ->withToken('test-token')
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

        $this
            ->withToken('test-token')
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
