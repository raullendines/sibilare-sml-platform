<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboards\Actions\SaveDashboardLayout;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDashboardLayoutRequest;
use App\Http\Resources\DashboardResource;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Dashboard;

class ClientDashboardLayoutController extends Controller
{
    public function __invoke(
        SaveDashboardLayoutRequest $request,
        Client $client,
        Dashboard $dashboard,
        SaveDashboardLayout $saveDashboardLayout
    ): DashboardResource {
        abort_unless($dashboard->client_id === $client->id, 404);

        $clientUser = $request->attributes->get('client_user');
        abort_unless($clientUser instanceof ClientUser, 403);

        return new DashboardResource(
            $saveDashboardLayout->handle($dashboard, $clientUser, $request->validated())
        );
    }
}
