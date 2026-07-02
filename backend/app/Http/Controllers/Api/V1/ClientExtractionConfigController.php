<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExtractionConfigRequest;
use App\Http\Requests\UpdateExtractionConfigRequest;
use App\Http\Resources\ExtractionConfigResource;
use App\Models\Client;
use App\Models\ExtractionConfig;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientExtractionConfigController extends Controller
{
    public function index(Client $client): AnonymousResourceCollection
    {
        return ExtractionConfigResource::collection(
            $client->extractionConfigs()
                ->with(['brand', 'platform', 'project', 'client'])
                ->latest('created_at')
                ->paginate(50)
        );
    }

    public function store(StoreExtractionConfigRequest $request, Client $client): ExtractionConfigResource
    {
        $data = $request->validated();
        $data['retroactive_days'] ??= 3;
        $data['selection_strategy'] ??= 'most_relevant';
        $data['is_active'] = $request->boolean('is_active', true);

        return new ExtractionConfigResource(
            $client->extractionConfigs()
                ->create($data)
                ->load(['brand', 'platform', 'project', 'client'])
        );
    }

    public function show(Client $client, ExtractionConfig $extractionConfig): ExtractionConfigResource
    {
        $this->ensureConfigBelongsToClient($extractionConfig, $client);

        return new ExtractionConfigResource($extractionConfig->load(['brand', 'platform', 'project', 'client']));
    }

    public function update(
        UpdateExtractionConfigRequest $request,
        Client $client,
        ExtractionConfig $extractionConfig
    ): ExtractionConfigResource {
        $this->ensureConfigBelongsToClient($extractionConfig, $client);

        $extractionConfig->fill($request->validated());
        $extractionConfig->save();

        return new ExtractionConfigResource($extractionConfig->refresh()->load(['brand', 'platform', 'project', 'client']));
    }

    private function ensureConfigBelongsToClient(ExtractionConfig $extractionConfig, Client $client): void
    {
        abort_unless($extractionConfig->client_id === $client->id, 404);
    }
}
