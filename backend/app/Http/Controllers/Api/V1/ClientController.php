<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Clients\Actions\CreateClient;
use App\Domain\Clients\Actions\UpdateClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $authUserId = (string) $request->attributes->get('supabase_user_id');

        return ClientResource::collection(
            Client::query()
                ->whereHas('users', fn ($query) => $query
                    ->where('auth_user_id', $authUserId)
                    ->whereNull('disabled_at'))
                ->latest('created_at')
                ->paginate(25)
        );
    }

    public function store(StoreClientRequest $request, CreateClient $createClient): ClientResource
    {
        $client = $createClient->handle($request->validated());

        $client->users()->create([
            'auth_user_id' => (string) $request->attributes->get('supabase_user_id'),
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        return new ClientResource($client);
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
