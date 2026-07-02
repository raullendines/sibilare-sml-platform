<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Extraction\Actions\KickoffManualExtractionBatch;
use App\Domain\Extraction\Actions\LaunchExtractionBatch;
use App\Domain\Extraction\Actions\SummarizeExtractionBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExtractionBatchRequest;
use App\Http\Resources\ExtractionBatchResource;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\ExtractionBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientExtractionBatchController extends Controller
{
    public function index(Client $client, SummarizeExtractionBatch $summarize): AnonymousResourceCollection
    {
        $batches = $client->extractionBatches()
            ->with('project')
            ->latest('launched_at')
            ->paginate(20);

        $batches->getCollection()->transform(
            fn (ExtractionBatch $batch) => $summarize->handle($batch)
        );

        return ExtractionBatchResource::collection($batches);
    }

    public function store(
        StoreExtractionBatchRequest $request,
        Client $client,
        LaunchExtractionBatch $launchExtractionBatch,
        KickoffManualExtractionBatch $kickoffManualExtractionBatch,
    ): ExtractionBatchResource {
        $clientUser = $request->attributes->get('client_user');
        abort_unless($clientUser instanceof ClientUser, 403);

        $project = null;
        $projectId = $request->validated('project_id');

        if (is_string($projectId) && $projectId !== '') {
            $project = $client->projects()->findOrFail($projectId);
        }

        $batch = $launchExtractionBatch->handle(
            $client,
            $clientUser,
            $project,
            $request->validated('config_ids'),
        );
        $batch = $kickoffManualExtractionBatch->handle($batch);

        return new ExtractionBatchResource(
            $batch->load([
                'project',
                'jobs.extractionConfig.brand',
                'jobs.extractionConfig.platform',
                'jobs.extractionConfig.project',
                'jobs.extractionConfig.client',
                'jobs.latestRun.agent',
            ])
        );
    }

    public function show(
        Request $request,
        Client $client,
        ExtractionBatch $extractionBatch,
        SummarizeExtractionBatch $summarize,
    ): ExtractionBatchResource {
        $this->ensureBelongsToClient($client, $extractionBatch);

        return new ExtractionBatchResource(
            $summarize->handle($extractionBatch)
                ->load([
                    'project',
                    'jobs.extractionConfig.brand',
                    'jobs.extractionConfig.platform',
                    'jobs.extractionConfig.project',
                    'jobs.extractionConfig.client',
                    'jobs.latestRun.agent',
                ])
        );
    }

    private function ensureBelongsToClient(Client $client, ExtractionBatch $batch): void
    {
        abort_unless($batch->client_id === $client->id, 404);
    }
}
