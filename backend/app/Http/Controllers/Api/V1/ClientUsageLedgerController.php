<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsageLedgerResource;
use App\Models\Client;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientUsageLedgerController extends Controller
{
    public function __invoke(Client $client): AnonymousResourceCollection
    {
        return UsageLedgerResource::collection(
            $client->usageLedger()
                ->with(['brand', 'platform'])
                ->latest('occurred_at')
                ->paginate(50)
        );
    }
}
