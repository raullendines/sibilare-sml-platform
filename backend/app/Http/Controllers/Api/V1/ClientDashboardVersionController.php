<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardVersionResource;
use App\Models\Client;
use App\Models\Dashboard;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientDashboardVersionController extends Controller
{
    public function __invoke(Client $client, Dashboard $dashboard): AnonymousResourceCollection
    {
        abort_unless($dashboard->client_id === $client->id, 404);

        return DashboardVersionResource::collection(
            $dashboard->versions()->paginate(25)
        );
    }
}
