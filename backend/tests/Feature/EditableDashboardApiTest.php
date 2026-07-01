<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Dashboard;
use App\Models\MetricDefinition;
use App\Models\WidgetTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EditableDashboardApiTest extends TestCase
{
    private string $authUserId = '11111111-1111-4111-8111-111111111111';

    private Client $client;

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

        $this->createSchema();
        $this->seedCatalog();

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
    }

    public function test_owner_can_create_save_and_publish_editable_dashboard(): void
    {
        $this
            ->withToken('valid-supabase-token')
            ->getJson('/api/v1/widget-templates')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'feed-latest-mentions');

        $createResponse = $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/dashboards", [
                'name' => 'Escucha social',
                'starter_template' => 'social-listening-overview',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Escucha social')
            ->assertJsonPath('data.layout_mode', 'freeform')
            ->assertJsonCount(6, 'data.widgets')
            ->assertJsonCount(3, 'data.filters');

        $dashboardId = $createResponse->json('data.id');
        $existingWidget = $createResponse->json('data.widgets.0');
        $existingFilter = $createResponse->json('data.filters.0');

        $layoutResponse = $this
            ->withToken('valid-supabase-token')
            ->putJson("/api/v1/clients/{$this->client->id}/dashboards/{$dashboardId}/layout", [
                'widgets' => [
                    [
                        'id' => $existingWidget['id'],
                        'widget_template_id' => $existingWidget['widget_template_id'],
                        'widget_type' => 'kpi',
                        'visualization_type' => 'kpi',
                        'metric_code' => 'mentions.total',
                        'title' => 'Menciones del periodo',
                        'grid_x' => 0,
                        'grid_y' => 0,
                        'grid_width' => 4,
                        'grid_height' => 2,
                        'sort_order' => 0,
                        'config' => ['date_range' => '30d'],
                        'filters' => [],
                        'is_visible' => true,
                    ],
                ],
                'filters' => [
                    [
                        'id' => $existingFilter['id'],
                        'field_code' => 'date_range',
                        'label' => 'Periodo',
                        'filter_type' => 'date_range',
                        'default_value' => '30d',
                        'config' => [],
                        'sort_order' => 0,
                        'is_visible' => true,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.widgets.0.title', 'Menciones del periodo')
            ->assertJsonCount(1, 'data.widgets')
            ->assertJsonCount(1, 'data.filters');

        $layoutResponse->assertJsonPath('data.widgets.0.grid_width', 4);

        $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/dashboards/{$dashboardId}/publish")
            ->assertCreated()
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('data.snapshot.dashboard.id', $dashboardId)
            ->assertJsonCount(1, 'data.snapshot.widgets');

        $this->assertDatabaseHas('dashboards', [
            'id' => $dashboardId,
            'status' => 'published',
            'current_version_number' => 1,
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/dashboards/{$dashboardId}/versions")
            ->assertOk()
            ->assertJsonPath('data.0.version_number', 1);
    }

    public function test_viewer_can_read_but_cannot_edit_dashboards(): void
    {
        $dashboard = Dashboard::create([
            'client_id' => $this->client->id,
            'name' => 'Readonly dashboard',
            'slug' => 'readonly-dashboard',
            'status' => 'draft',
            'grid_columns' => 12,
        ]);

        $this->client->users()
            ->where('auth_user_id', $this->authUserId)
            ->update(['role' => 'viewer']);

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/dashboards/{$dashboard->id}")
            ->assertOk();

        $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/dashboards", [
                'name' => 'Forbidden dashboard',
            ])
            ->assertForbidden();

        $this
            ->withToken('valid-supabase-token')
            ->putJson("/api/v1/clients/{$this->client->id}/dashboards/{$dashboard->id}/layout", [
                'widgets' => [],
                'filters' => [],
            ])
            ->assertForbidden();
    }

    public function test_user_can_persist_dashboard_filter_preferences(): void
    {
        $dashboard = $this->createDashboard('Preference test');

        $this
            ->withToken('valid-supabase-token')
            ->putJson("/api/v1/clients/{$this->client->id}/dashboards/{$dashboard['id']}/preferences", [
                'filter_values' => [
                    'date_range' => '7d',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.filter_values.date_range', '7d');

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/dashboards/{$dashboard['id']}")
            ->assertOk()
            ->assertJsonPath('data.viewer_preferences.filter_values.date_range', '7d');
    }

    public function test_editor_can_change_dashboard_layout_mode(): void
    {
        $dashboard = $this->createDashboard('Guided layout');

        $this
            ->withToken('valid-supabase-token')
            ->patchJson("/api/v1/clients/{$this->client->id}/dashboards/{$dashboard['id']}", [
                'layout_mode' => 'guided',
            ])
            ->assertOk()
            ->assertJsonPath('data.layout_mode', 'guided');
    }

    public function test_dashboard_routes_reject_cross_client_dashboard_ids(): void
    {
        $otherClient = Client::create([
            'name' => 'Other Client',
            'slug' => 'other-client',
            'status' => 'active',
            'default_locale' => 'es-ES',
            'timezone' => 'Europe/Madrid',
        ]);

        $otherClient->users()->create([
            'auth_user_id' => $this->authUserId,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        $otherDashboard = Dashboard::create([
            'client_id' => $otherClient->id,
            'name' => 'Other dashboard',
            'slug' => 'other-dashboard',
            'status' => 'draft',
            'grid_columns' => 12,
        ]);

        $this
            ->withToken('valid-supabase-token')
            ->getJson("/api/v1/clients/{$this->client->id}/dashboards/{$otherDashboard->id}")
            ->assertNotFound();
    }

    public function test_layout_rejects_widget_ids_from_another_dashboard(): void
    {
        $firstDashboard = $this->createDashboard('First dashboard');
        $secondDashboard = $this->createDashboard('Second dashboard');
        $foreignWidget = $secondDashboard['widgets'][0];

        $this
            ->withToken('valid-supabase-token')
            ->putJson("/api/v1/clients/{$this->client->id}/dashboards/{$firstDashboard['id']}/layout", [
                'widgets' => [
                    [
                        'id' => $foreignWidget['id'],
                        'widget_template_id' => $foreignWidget['widget_template_id'],
                        'widget_type' => $foreignWidget['widget_type'],
                        'visualization_type' => $foreignWidget['visualization_type'],
                        'metric_code' => $foreignWidget['metric_code'],
                        'title' => $foreignWidget['title'],
                        'grid_x' => 0,
                        'grid_y' => 0,
                        'grid_width' => 3,
                        'grid_height' => 2,
                        'sort_order' => 0,
                        'config' => [],
                        'filters' => [],
                        'is_visible' => true,
                    ],
                ],
                'filters' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('widgets.0.id');
    }

    public function test_layout_rejects_widgets_that_exceed_the_dashboard_grid(): void
    {
        $dashboard = $this->createDashboard('Grid test');
        $widget = $dashboard['widgets'][0];

        $this
            ->withToken('valid-supabase-token')
            ->putJson("/api/v1/clients/{$this->client->id}/dashboards/{$dashboard['id']}/layout", [
                'widgets' => [
                    [
                        'id' => $widget['id'],
                        'widget_template_id' => $widget['widget_template_id'],
                        'widget_type' => $widget['widget_type'],
                        'visualization_type' => $widget['visualization_type'],
                        'metric_code' => $widget['metric_code'],
                        'title' => $widget['title'],
                        'grid_x' => 10,
                        'grid_y' => 0,
                        'grid_width' => 3,
                        'grid_height' => 2,
                        'sort_order' => 0,
                        'config' => [],
                        'filters' => [],
                        'is_visible' => true,
                    ],
                ],
                'filters' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('widgets.0.grid_width');
    }

    /**
     * @return array<string, mixed>
     */
    private function createDashboard(string $name): array
    {
        return $this
            ->withToken('valid-supabase-token')
            ->postJson("/api/v1/clients/{$this->client->id}/dashboards", [
                'name' => $name,
                'starter_template' => 'social-listening-overview',
            ])
            ->assertCreated()
            ->json('data');
    }

    private function seedCatalog(): void
    {
        $metrics = [
            ['mentions.total', 'Total mentions', 'posts', 'number', 'count', 'kpi'],
            ['mentions.relevant', 'Relevant mentions', 'posts', 'number', 'count', 'kpi'],
            ['mentions.timeline', 'Mentions over time', 'posts', 'number', 'count', 'line'],
            ['mentions.by_platform', 'Mentions by platform', 'posts', 'number', 'count', 'bar'],
            ['mentions.latest', 'Latest mentions', 'posts', 'list', 'latest', 'mentions_feed'],
            ['usage.cost', 'Usage cost', 'usage', 'currency', 'sum', 'kpi'],
        ];

        foreach ($metrics as [$code, $name, $domain, $valueType, $aggregation, $visualization]) {
            MetricDefinition::create([
                'code' => $code,
                'name' => $name,
                'source_domain' => $domain,
                'value_type' => $valueType,
                'default_aggregation' => $aggregation,
                'default_visualization_type' => $visualization,
                'config_schema' => [],
                'is_active' => true,
            ]);
        }

        $templates = [
            ['kpi-total-mentions', 'Total mentions', 'overview', 'kpi', 'mentions.total', 'kpi', 3, 2],
            ['kpi-relevant-mentions', 'Relevant mentions', 'overview', 'kpi', 'mentions.relevant', 'kpi', 3, 2],
            ['kpi-usage-cost', 'Usage cost', 'operations', 'kpi', 'usage.cost', 'kpi', 3, 2],
            ['line-mentions-timeline', 'Mentions over time', 'mentions', 'line', 'mentions.timeline', 'line', 8, 4],
            ['bar-mentions-platform', 'Mentions by platform', 'mentions', 'bar', 'mentions.by_platform', 'bar', 4, 4],
            ['feed-latest-mentions', 'Latest mentions', 'mentions', 'mentions_feed', 'mentions.latest', 'mentions_feed', 12, 5],
        ];

        foreach ($templates as [$code, $title, $category, $widgetType, $metricCode, $visualization, $width, $height]) {
            WidgetTemplate::create([
                'code' => $code,
                'name' => $title,
                'category' => $category,
                'widget_type' => $widgetType,
                'metric_code' => $metricCode,
                'default_title' => $title,
                'default_visualization_type' => $visualization,
                'default_config' => ['date_range' => '30d'],
                'default_width' => $width,
                'default_height' => $height,
                'min_width' => 2,
                'min_height' => 2,
                'is_active' => true,
            ]);
        }
    }

    private function createSchema(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('name');
            $table->text('slug')->unique();
            $table->text('status');
            $table->text('industry')->nullable();
            $table->text('default_locale');
            $table->text('timezone');
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

        Schema::create('widget_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('code')->unique();
            $table->text('name');
            $table->text('description')->nullable();
            $table->text('category');
            $table->text('widget_type');
            $table->text('metric_code')->nullable();
            $table->text('default_title');
            $table->text('default_visualization_type');
            $table->json('default_config');
            $table->integer('default_width');
            $table->integer('default_height');
            $table->integer('min_width');
            $table->integer('min_height');
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('dashboards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->text('name');
            $table->text('slug');
            $table->text('description')->nullable();
            $table->text('status');
            $table->boolean('is_default')->default(false);
            $table->integer('grid_columns')->default(12);
            $table->text('layout_mode')->default('freeform');
            $table->integer('current_version_number')->default(0);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dashboard_widgets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('dashboard_id');
            $table->uuid('widget_template_id')->nullable();
            $table->text('widget_type');
            $table->text('visualization_type');
            $table->text('metric_code')->nullable();
            $table->text('title');
            $table->text('description')->nullable();
            $table->integer('grid_x');
            $table->integer('grid_y');
            $table->integer('grid_width');
            $table->integer('grid_height');
            $table->integer('min_width');
            $table->integer('min_height');
            $table->integer('sort_order');
            $table->json('config');
            $table->json('filters');
            $table->boolean('is_visible');
            $table->timestamps();
        });

        Schema::create('dashboard_filters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('dashboard_id');
            $table->text('field_code');
            $table->text('label');
            $table->text('filter_type');
            $table->json('default_value')->nullable();
            $table->json('config');
            $table->integer('sort_order');
            $table->boolean('is_visible');
            $table->timestamps();
        });

        Schema::create('dashboard_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('dashboard_id');
            $table->integer('version_number');
            $table->json('snapshot');
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('dashboard_user_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('dashboard_id');
            $table->uuid('client_user_id');
            $table->json('filter_values');
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
        });

        Schema::create('client_platforms', function (Blueprint $table): void {
            $table->uuid('client_id');
            $table->uuid('platform_id');
            $table->boolean('enabled');
        });
    }
}
