<?php

namespace App\Domain\Dashboards\Actions;

use App\Models\ClientUser;
use App\Models\Dashboard;
use App\Models\DashboardVersion;
use Illuminate\Support\Facades\DB;

class PublishDashboard
{
    public function handle(Dashboard $dashboard, ClientUser $clientUser): DashboardVersion
    {
        return DB::transaction(function () use ($dashboard, $clientUser): DashboardVersion {
            $lockedDashboard = Dashboard::query()
                ->whereKey($dashboard->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->load(['widgets.template', 'widgets.metric', 'filters']);

            $versionNumber = $lockedDashboard->current_version_number + 1;

            $version = $lockedDashboard->versions()->create([
                'client_id' => $lockedDashboard->client_id,
                'version_number' => $versionNumber,
                'snapshot' => [
                    'dashboard' => $lockedDashboard->only([
                        'id',
                        'client_id',
                        'name',
                        'slug',
                        'description',
                        'grid_columns',
                    ]),
                    'widgets' => $lockedDashboard->widgets->toArray(),
                    'filters' => $lockedDashboard->filters->toArray(),
                ],
                'created_by_user_id' => $clientUser->id,
            ]);

            $lockedDashboard->forceFill([
                'status' => 'published',
                'current_version_number' => $versionNumber,
                'updated_by_user_id' => $clientUser->id,
                'published_at' => now(),
            ])->save();

            return $version;
        });
    }
}
