<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboards\Actions\QueryClientMetrics;
use App\Http\Controllers\Controller;
use App\Http\Requests\QueryClientMetricsRequest;
use App\Http\Resources\PostResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientMetricQueryController extends Controller
{
    public function __invoke(
        QueryClientMetricsRequest $request,
        Client $client,
        QueryClientMetrics $queryClientMetrics,
    ): JsonResponse {
        $results = $queryClientMetrics->handle($client, $request->validated('queries'));

        foreach ($results as &$result) {
            if ($result['kind'] === 'list') {
                $result['items'] = PostResource::collection($result['items'])->resolve($request);
            }
        }

        return response()->json(['data' => $results]);
    }
}
