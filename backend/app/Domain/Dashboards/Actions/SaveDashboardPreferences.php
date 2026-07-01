<?php

namespace App\Domain\Dashboards\Actions;

use App\Models\ClientUser;
use App\Models\Dashboard;
use App\Models\DashboardUserPreference;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveDashboardPreferences
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Dashboard $dashboard, ClientUser $clientUser, array $data): DashboardUserPreference
    {
        return DB::transaction(function () use ($dashboard, $clientUser, $data): DashboardUserPreference {
            $allowedFields = $dashboard->filters()->pluck('field_code')->all();
            $filterValues = Arr::only($data['filter_values'] ?? [], $allowedFields);

            /** @var DashboardUserPreference $preference */
            $preference = DashboardUserPreference::query()->firstOrNew([
                'dashboard_id' => $dashboard->id,
                'client_user_id' => $clientUser->id,
            ]);

            $preference->fill([
                'client_id' => $dashboard->client_id,
                'filter_values' => $filterValues,
                'last_opened_at' => now(),
            ]);
            $preference->save();

            return $preference->refresh();
        });
    }
}
