<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Extraction\Actions\SummarizeExtractionBatch;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExtractionBatchResource;
use App\Http\Resources\ExtractionConfigResource;
use App\Http\Resources\ProjectResource;
use App\Models\Client;
use App\Models\ExtractionBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientExtractionWorkspaceController extends Controller
{
    public function __invoke(
        Request $request,
        Client $client,
        SummarizeExtractionBatch $summarize,
    ): JsonResponse {
        $projects = $client->projects()
            ->with('brands')
            ->withCount(['dashboards', 'extractionConfigs'])
            ->orderBy('name')
            ->get();

        $configs = $client->extractionConfigs()
            ->with(['brand', 'platform', 'project', 'client'])
            ->latest('created_at')
            ->limit(50)
            ->get();

        $batches = $client->extractionBatches()
            ->with('project')
            ->latest('launched_at')
            ->limit(20)
            ->get()
            ->map(fn (ExtractionBatch $batch) => $summarize->handle($batch))
            ->values();

        return response()->json([
            'data' => [
                'projects' => ProjectResource::collection($projects)->resolve($request),
                'configs' => ExtractionConfigResource::collection($configs)->resolve($request),
                'batches' => ExtractionBatchResource::collection($batches)->resolve($request),
            ],
        ]);
    }
}
