<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Clients\Actions\CreateClient;
use App\Domain\Clients\Actions\UpdateClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ClientResource::collection(
            Client::query()
                ->latest('created_at')
                ->paginate(25)
        );
    }

    public function store(StoreClientRequest $request, CreateClient $createClient): ClientResource
    {
        return new ClientResource(
            $createClient->handle($request->validated())
        );
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client);
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClient $updateClient): ClientResource
    {
        return new ClientResource(
            $updateClient->handle($client, $request->validated())
        );
    }
}
