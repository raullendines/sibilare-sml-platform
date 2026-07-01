<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformResource;
use App\Models\Client;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientPlatformController extends Controller
{
    public function __invoke(Client $client): AnonymousResourceCollection
    {
        $platforms = $client->platforms()
            ->where('enabled', true)
            ->with('platform')
            ->get()
            ->pluck('platform')
            ->filter()
            ->sortBy('name')
            ->values();

        return PlatformResource::collection($platforms);
    }
}
