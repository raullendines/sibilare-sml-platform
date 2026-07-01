<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboards\Actions\SaveDashboardPreferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDashboardPreferencesRequest;
use App\Http\Resources\DashboardUserPreferenceResource;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Dashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class ClientDashboardPreferenceController extends Controller
{
    public function __invoke(
        SaveDashboardPreferencesRequest $request,
        Client $client,
        Dashboard $dashboard,
        SaveDashboardPreferences $saveDashboardPreferences
    ): JsonResponse {
        abort_unless($dashboard->client_id === $client->id, 404);
        abort_unless(
            Schema::hasTable('dashboard_user_preferences'),
            409,
            'Las preferencias por usuario del dashboard aun no estan disponibles en esta base de datos.',
        );

        $clientUser = $request->attributes->get('client_user');
        abort_unless($clientUser instanceof ClientUser, 403);

        return (new DashboardUserPreferenceResource(
            $saveDashboardPreferences->handle($dashboard, $clientUser, $request->validated())
        ))->response()->setStatusCode(200);
    }
}
