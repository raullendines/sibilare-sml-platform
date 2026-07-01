<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboards\Actions\PublishDashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublishDashboardRequest;
use App\Http\Resources\DashboardVersionResource;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Dashboard;

class ClientDashboardPublishController extends Controller
{
    public function __invoke(
        PublishDashboardRequest $request,
        Client $client,
        Dashboard $dashboard,
        PublishDashboard $publishDashboard
    ): DashboardVersionResource {
        abort_unless($dashboard->client_id === $client->id, 404);

        $clientUser = $request->attributes->get('client_user');
        abort_unless($clientUser instanceof ClientUser, 403);

        return new DashboardVersionResource(
            $publishDashboard->handle($dashboard, $clientUser)
        );
    }
}
