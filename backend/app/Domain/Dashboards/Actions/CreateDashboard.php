<?php

namespace App\Domain\Dashboards\Actions;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Dashboard;
use App\Models\WidgetTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateDashboard
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, ClientUser $clientUser, array $data): Dashboard
    {
        return DB::transaction(function () use ($client, $clientUser, $data): Dashboard {
            $isDefault = (bool) ($data['is_default'] ?? ! $client->dashboards()->exists());

            if ($isDefault) {
                $client->dashboards()->update(['is_default' => false]);
            }

            $attributes = [
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($client, $data['slug'] ?? $data['name']),
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'is_default' => $isDefault,
                'grid_columns' => $data['grid_columns'] ?? 12,
                'layout_mode' => $data['layout_mode'] ?? 'freeform',
                'created_by_user_id' => $clientUser->id,
                'updated_by_user_id' => $clientUser->id,
            ];

            if (Schema::hasColumn('dashboards', 'project_id')) {
                $attributes['project_id'] = $data['project_id'] ?? null;
            }

            $dashboard = $client->dashboards()->create($attributes);

            $this->createDefaultFilters($dashboard);

            if (($data['starter_template'] ?? 'social-listening-overview') === 'social-listening-overview') {
                $this->createOverviewWidgets($dashboard);
            }

            return $dashboard->load(['widgets.template', 'widgets.metric', 'filters']);
        });
    }

    private function uniqueSlug(Client $client, string $value): string
    {
        $base = Str::slug($value) ?: 'dashboard';
        $slug = $base;
        $suffix = 2;

        while ($client->dashboards()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function createDefaultFilters(Dashboard $dashboard): void
    {
        $filters = [
            ['date_range', 'Period', 'date_range', '30d'],
            ['brand_ids', 'Brands', 'multi_select', []],
            ['platform_ids', 'Platforms', 'multi_select', []],
        ];

        foreach ($filters as $index => [$fieldCode, $label, $filterType, $defaultValue]) {
            $dashboard->filters()->create([
                'client_id' => $dashboard->client_id,
                'field_code' => $fieldCode,
                'label' => $label,
                'filter_type' => $filterType,
                'default_value' => $defaultValue,
                'config' => [],
                'sort_order' => $index,
                'is_visible' => true,
            ]);
        }
    }

    private function createOverviewWidgets(Dashboard $dashboard): void
    {
        $placements = [
            ['kpi-total-mentions', 0, 0, 3, 2],
            ['kpi-relevant-mentions', 3, 0, 3, 2],
            ['kpi-usage-cost', 6, 0, 3, 2],
            ['line-mentions-timeline', 0, 2, 8, 4],
            ['bar-mentions-platform', 8, 2, 4, 4],
            ['feed-latest-mentions', 0, 6, 12, 5],
        ];

        $templates = WidgetTemplate::query()
            ->whereIn('code', array_column($placements, 0))
            ->get()
            ->keyBy('code');

        foreach ($placements as $sortOrder => [$templateCode, $x, $y, $width, $height]) {
            $template = $templates->get($templateCode);

            if (! $template instanceof WidgetTemplate) {
                continue;
            }

            $dashboard->widgets()->create([
                'client_id' => $dashboard->client_id,
                'widget_template_id' => $template->id,
                'widget_type' => $template->widget_type,
                'visualization_type' => $template->default_visualization_type,
                'metric_code' => $template->metric_code,
                'title' => $template->default_title,
                'grid_x' => $x,
                'grid_y' => $y,
                'grid_width' => $width,
                'grid_height' => $height,
                'min_width' => $template->min_width,
                'min_height' => $template->min_height,
                'sort_order' => $sortOrder,
                'config' => $template->default_config,
                'filters' => [],
                'is_visible' => true,
            ]);
        }
    }
}
