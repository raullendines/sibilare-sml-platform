<?php

namespace App\Domain\Dashboards\Actions;

use App\Models\ClientUser;
use App\Models\Dashboard;
use Illuminate\Support\Facades\DB;

class UpdateDashboard
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Dashboard $dashboard, ClientUser $clientUser, array $data): Dashboard
    {
        return DB::transaction(function () use ($dashboard, $clientUser, $data): Dashboard {
            if (($data['is_default'] ?? false) === true) {
                Dashboard::query()
                    ->where('client_id', $dashboard->client_id)
                    ->whereKeyNot($dashboard->id)
                    ->update(['is_default' => false]);
            }

            if (($data['status'] ?? null) === 'archived') {
                $data['is_default'] = false;
            }

            $dashboard->fill($data);
            $dashboard->updated_by_user_id = $clientUser->id;
            $dashboard->save();

            return $dashboard->refresh()->loadCount('widgets');
        });
    }
}
