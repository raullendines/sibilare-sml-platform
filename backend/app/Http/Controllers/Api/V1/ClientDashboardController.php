<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboards\Actions\CreateDashboard;
use App\Domain\Dashboards\Actions\UpdateDashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDashboardRequest;
use App\Http\Requests\UpdateDashboardRequest;
use App\Http\Resources\DashboardResource;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Dashboard;
use App\Models\DashboardUserPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Schema;

class ClientDashboardController extends Controller
{
    public function index(Client $client): AnonymousResourceCollection
    {
        return DashboardResource::collection(
            $client->dashboards()
                ->withCount('widgets')
                ->where('status', '<>', 'archived')
                ->orderByDesc('is_default')
                ->latest('updated_at')
                ->get()
        );
    }

    public function store(
        StoreDashboardRequest $request,
        Client $client,
        CreateDashboard $createDashboard
    ): DashboardResource {
        return new DashboardResource(
            $createDashboard->handle($client, $this->clientUser($request), $request->validated())
        );
    }

    public function show(Request $request, Client $client, Dashboard $dashboard): DashboardResource
    {
        $this->ensureDashboardBelongsToClient($dashboard, $client);

        $clientUser = $request->attributes->get('client_user');
        $loadedDashboard = $dashboard->load(['widgets.template.metric', 'widgets.metric', 'filters']);
        $preferencesSupported = $this->dashboardUserPreferencesAvailable();
        $loadedDashboard->setRelation(
            'currentUserPreference',
            $preferencesSupported && $clientUser instanceof ClientUser
                ? DashboardUserPreference::query()
                    ->where('dashboard_id', $dashboard->id)
                    ->where('client_user_id', $clientUser->id)
                    ->first()
                : null
        );
        $loadedDashboard->setAttribute('preferences_supported', $preferencesSupported);

        return new DashboardResource(
            $loadedDashboard
        );
    }

    public function update(
        UpdateDashboardRequest $request,
        Client $client,
        Dashboard $dashboard,
        UpdateDashboard $updateDashboard
    ): DashboardResource {
        $this->ensureDashboardBelongsToClient($dashboard, $client);

        return new DashboardResource(
            $updateDashboard->handle($dashboard, $this->clientUser($request), $request->validated())
        );
    }

    private function ensureDashboardBelongsToClient(Dashboard $dashboard, Client $client): void
    {
        abort_unless($dashboard->client_id === $client->id, 404);
    }

    private function clientUser(StoreDashboardRequest|UpdateDashboardRequest $request): ClientUser
    {
        $clientUser = $request->attributes->get('client_user');

        abort_unless($clientUser instanceof ClientUser, 403);

        return $clientUser;
    }

    private function dashboardUserPreferencesAvailable(): bool
    {
        static $available;

        if ($available !== null) {
            return $available;
        }

        $available = Schema::hasTable('dashboard_user_preferences');

        return $available;
    }
}
