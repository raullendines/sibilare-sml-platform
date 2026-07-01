<?php

namespace App\Domain\Dashboards\Actions;

use App\Models\ClientUser;
use App\Models\Dashboard;
use App\Models\DashboardFilter;
use App\Models\DashboardWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDashboardLayout
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Dashboard $dashboard, ClientUser $clientUser, array $data): Dashboard
    {
        return DB::transaction(function () use ($dashboard, $clientUser, $data): Dashboard {
            $this->saveWidgets($dashboard, $data['widgets']);
            $this->saveFilters($dashboard, $data['filters']);

            $dashboard->forceFill([
                'status' => 'draft',
                'updated_by_user_id' => $clientUser->id,
            ])->save();

            return $dashboard->refresh()->load(['widgets.template', 'widgets.metric', 'filters']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $widgets
     */
    private function saveWidgets(Dashboard $dashboard, array $widgets): void
    {
        $existing = $dashboard->widgets()->get()->keyBy('id');
        $savedIds = [];
        $metriclessTypes = ['text', 'heading', 'divider'];

        foreach ($widgets as $index => $widgetData) {
            if ($dashboard->grid_columns < $widgetData['grid_x'] + $widgetData['grid_width']) {
                throw ValidationException::withMessages([
                    "widgets.{$index}.grid_width" => 'The widget exceeds the dashboard grid width.',
                ]);
            }

            if (! in_array($widgetData['widget_type'], $metriclessTypes, true) && empty($widgetData['metric_code'])) {
                throw ValidationException::withMessages([
                    "widgets.{$index}.metric_code" => 'A metric is required for data widgets.',
                ]);
            }

            $widget = $this->resolveWidget($existing, $widgetData['id'] ?? null, $index);
            $widget->fill([
                ...$widgetData,
                'client_id' => $dashboard->client_id,
                'dashboard_id' => $dashboard->id,
                'min_width' => $widgetData['min_width'] ?? 2,
                'min_height' => $widgetData['min_height'] ?? 2,
                'config' => $widgetData['config'] ?? [],
                'filters' => $widgetData['filters'] ?? [],
                'is_visible' => $widgetData['is_visible'] ?? true,
            ]);
            $widget->save();
            $savedIds[] = $widget->id;
        }

        $widgetsToDelete = $dashboard->widgets();

        if ($savedIds !== []) {
            $widgetsToDelete->whereNotIn('id', $savedIds);
        }

        $widgetsToDelete->delete();
    }

    /**
     * @param  Collection<string, DashboardWidget>  $existing
     */
    private function resolveWidget(
        Collection $existing,
        ?string $widgetId,
        int $index
    ): DashboardWidget {
        if ($widgetId === null) {
            return new DashboardWidget;
        }

        $widget = $existing->get($widgetId);

        if (! $widget instanceof DashboardWidget) {
            throw ValidationException::withMessages([
                "widgets.{$index}.id" => 'The widget does not belong to this dashboard.',
            ]);
        }

        return $widget;
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     */
    private function saveFilters(Dashboard $dashboard, array $filters): void
    {
        $existing = $dashboard->filters()->get()->keyBy('id');
        $savedIds = [];

        foreach ($filters as $index => $filterData) {
            $filter = $this->resolveFilter($existing, $filterData['id'] ?? null, $index);
            $filter->fill([
                ...$filterData,
                'client_id' => $dashboard->client_id,
                'dashboard_id' => $dashboard->id,
                'config' => $filterData['config'] ?? [],
                'is_visible' => $filterData['is_visible'] ?? true,
            ]);
            $filter->save();
            $savedIds[] = $filter->id;
        }

        $filtersToDelete = $dashboard->filters();

        if ($savedIds !== []) {
            $filtersToDelete->whereNotIn('id', $savedIds);
        }

        $filtersToDelete->delete();
    }

    /**
     * @param  Collection<string, DashboardFilter>  $existing
     */
    private function resolveFilter(Collection $existing, ?string $filterId, int $index): DashboardFilter
    {
        if ($filterId === null) {
            return new DashboardFilter;
        }

        $filter = $existing->get($filterId);

        if (! $filter instanceof DashboardFilter) {
            throw ValidationException::withMessages([
                "filters.{$index}.id" => 'The filter does not belong to this dashboard.',
            ]);
        }

        return $filter;
    }
}
