<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Http\Resources\PostResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientOverviewController extends Controller
{
    public function __invoke(Client $client): JsonResponse
    {
        $client->load('branding');

        return response()->json([
            'data' => [
                'client' => ClientResource::make($client),
                'counts' => [
                    'brands' => $client->brands()->count(),
                    'own_brands' => $client->brands()->whereIn('brand_type', ['own_brand', 'own_subbrand'])->count(),
                    'competitors' => $client->brands()->whereIn('brand_type', ['competitor', 'competitor_subbrand'])->count(),
                    'extraction_configs' => $client->extractionConfigs()->count(),
                    'posts' => $client->posts()->count(),
                    'usage_entries' => $client->usageLedger()->count(),
                ],
                'latest_posts' => PostResource::collection(
                    $client->posts()
                        ->with(['brand', 'platformPost.platform'])
                        ->latest('created_at')
                        ->limit(5)
                        ->get()
                ),
            ],
        ]);
    }
}
