<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\FinalizeApifyExtraction;
use App\Models\ExtractionRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApifyWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuredSecret = config('services.apify.webhook_secret');
        $providedSecret = $request->header('X-Apify-Webhook-Secret');

        if (! is_string($configuredSecret) || $configuredSecret === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Apify webhook secret is not configured.');
        }

        if (! is_string($providedSecret) || ! hash_equals($configuredSecret, $providedSecret)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid Apify webhook secret.');
        }

        $request->validate([
            'eventType' => ['required', 'string', 'in:ACTOR.RUN.SUCCEEDED,ACTOR.RUN.FAILED,ACTOR.RUN.ABORTED,ACTOR.RUN.TIMED_OUT'],
            'resource.id' => ['required', 'string', 'max:255'],
            'resource.defaultDatasetId' => ['nullable', 'string', 'max:255'],
        ]);

        $externalRunId = (string) $request->input('resource.id');
        $shouldDispatch = false;
        $run = DB::transaction(function () use ($externalRunId, $request, &$shouldDispatch): ?ExtractionRun {
            $candidate = ExtractionRun::query()
                ->where('external_run_id', $externalRunId)
                ->lockForUpdate()
                ->first();

            if (! $candidate || $candidate->status !== 'waiting_provider') {
                return $candidate;
            }

            $candidate->update([
                'status' => 'finalizing',
                'dataset_id' => $request->input('resource.defaultDatasetId', $candidate->dataset_id),
                'webhook_received_at' => now(),
            ]);
            $shouldDispatch = true;

            return $candidate->refresh();
        }, 3);

        if (! $run) {
            return response()->json(['accepted' => true, 'matched' => false], Response::HTTP_ACCEPTED);
        }

        if ($shouldDispatch) {
            FinalizeApifyExtraction::dispatch($run->id);
        }

        return response()->json(['accepted' => true, 'matched' => true], Response::HTTP_ACCEPTED);
    }
}
