<?php

namespace App\Domain\Extraction\Actions;

use App\Domain\Extraction\ApifyHttpClient;
use App\Domain\Extraction\Data\ApifyBilling;
use App\Jobs\ClaimAndLaunchExtraction;
use App\Models\ApifyAgent;
use App\Models\ExtractionRun;
use App\Models\UsageLedger;
use Illuminate\Support\Facades\DB;
use Throwable;

class FinalizeExtractionRun
{
    public function __construct(
        private readonly ApifyHttpClient $apify,
        private readonly CalculateApifyBilling $calculateBilling,
        private readonly StoreNormalizedExtractionItems $storeItems,
    ) {}

    public function handle(string $runId): void
    {
        $run = DB::transaction(function () use ($runId): ?ExtractionRun {
            $candidate = ExtractionRun::query()->lockForUpdate()->find($runId);

            if (! $candidate || ($candidate->status === 'finalizing' && $candidate->finalization_started_at !== null)
                || ! in_array($candidate->status, ['waiting_provider', 'finalizing'], true)) {
                return null;
            }

            $candidate->update(['status' => 'finalizing', 'finalization_started_at' => now()]);

            return $candidate->refresh();
        }, 3);

        if (! $run || ! $run->external_run_id) {
            return;
        }

        $run->load(['agent', 'job', 'extractionConfig.brand', 'extractionConfig.platform', 'extractionConfig.project']);

        try {
            $runData = $this->apify->getRun($run->external_run_id);
            $providerStatus = strtoupper(str_replace(['-', ' '], '_', (string) ($runData['status'] ?? '')));

            if (in_array($providerStatus, ['READY', 'RUNNING'], true)) {
                if ($run->started_at?->lte(now()->subMinutes((int) config('services.apify.stale_after_minutes', 90)))) {
                    $this->apify->abortRun($run->external_run_id);
                    $this->finishFailed($run, 'aborted', 'stale_provider_run', $runData);
                } else {
                    $run->update(['status' => 'waiting_provider', 'finalization_started_at' => null]);
                }

                return;
            }

            if ($providerStatus !== 'SUCCEEDED') {
                $status = match ($providerStatus) {
                    'ABORTED' => 'aborted',
                    'TIMED_OUT', 'TIMEDOUT' => 'timed_out',
                    default => 'failed',
                };
                $this->finishFailed($run, $status, 'provider_'.strtolower($providerStatus ?: 'unknown'), $runData);

                return;
            }

            $datasetId = $runData['defaultDatasetId'] ?? $run->dataset_id;

            if (! is_string($datasetId) || $datasetId === '') {
                $this->finishFailed($run, 'failed', 'missing_dataset', $runData);

                return;
            }

            $items = $this->apify->getDatasetItems($datasetId, (int) $run->posts_requested);
            $stats = $this->storeItems->handle($run, $items);
            $billing = $this->persistBilling($run, $runData);

            DB::transaction(function () use ($run, $datasetId, $stats, $billing): void {
                $run->update([
                    'dataset_id' => $datasetId,
                    'status' => 'success',
                    'posts_fetched' => $stats['fetched'],
                    'posts_stored' => $stats['stored'],
                    'posts_discarded' => $stats['discarded'],
                    'result_summary' => [
                        'billed_cost_breakdown' => $billing->breakdown,
                        'fetch_window' => [$run->fetch_start?->toIso8601String(), $run->fetch_end?->toIso8601String()],
                    ],
                    'finished_at' => now(),
                ]);
                $run->job?->update([
                    'status' => 'completed',
                    'reserved_cost_usd' => 0,
                    'completed_at' => now(),
                    'locked_at' => null,
                    'locked_by' => null,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $run->update([
                'status' => 'waiting_provider',
                'error_code' => 'finalization_failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'finalization_started_at' => null,
            ]);
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $runData
     */
    private function finishFailed(ExtractionRun $run, string $status, string $reason, array $runData): void
    {
        $this->persistBilling($run, $runData);
        $run->update([
            'status' => $status,
            'abort_reason' => $reason,
            'error_code' => $reason,
            'error_message' => isset($runData['statusMessage']) ? mb_substr((string) $runData['statusMessage'], 0, 2000) : null,
            'finished_at' => now(),
        ]);

        $job = $run->job;

        if (! $job) {
            return;
        }

        $hasFallback = ApifyAgent::query()
            ->where('platform_id', $run->platform_id)
            ->where('is_active', true)
            ->whereNotIn('id', $job->runs()->pluck('agent_id')->filter()->all())
            ->exists();
        $retryCount = $job->retry_count + 1;

        if ($hasFallback && $retryCount <= $job->max_retries) {
            $job->update([
                'status' => 'pending',
                'retry_count' => $retryCount,
                'next_retry_at' => now(),
                'locked_at' => null,
                'locked_by' => null,
            ]);
            ClaimAndLaunchExtraction::dispatch();

            return;
        }

        $job->update([
            'status' => 'failed',
            'retry_count' => $retryCount,
            'reserved_cost_usd' => 0,
            'completed_at' => now(),
            'locked_at' => null,
            'locked_by' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $runData
     */
    private function persistBilling(ExtractionRun $run, array $runData): ApifyBilling
    {
        $agent = $run->agent;

        if (! $agent instanceof ApifyAgent) {
            $snapshot = $run->pricing_snapshot ?? [];
            $agent = new ApifyAgent([
                'actor_id' => $snapshot['actor_id'] ?? 'deleted-actor',
                'billing_model' => $snapshot['billing_model'] ?? 'per_item',
                'pricing_unit' => $snapshot['pricing_unit'] ?? null,
                'cost_per_run_estimate' => $snapshot['cost_per_run_estimate'] ?? 0,
                'cost_per_item_estimate' => $snapshot['cost_per_item_estimate'] ?? 0,
                'pricing_details' => $snapshot['pricing_details'] ?? [],
            ]);
        }

        $billing = $this->calculateBilling->handle($agent, $runData);

        $run->update([
            'compute_units' => $billing->computeUnits,
            'usage_cost_usd' => $billing->usageCostUsd,
            'billed_cost_usd' => $billing->billedCostUsd,
            'cost_amount' => $billing->billedCostUsd,
            'currency' => 'USD',
            'charged_event_counts' => $billing->chargedEventCounts,
        ]);

        UsageLedger::query()->updateOrCreate(
            [
                'client_id' => $run->client_id,
                'usage_type' => 'apify_run',
                'source_table' => 'extraction_runs',
                'source_id' => $run->id,
            ],
            [
                'brand_id' => $run->brand_id,
                'platform_id' => $run->platform_id,
                'quantity' => 1,
                'unit' => 'run',
                'cost_amount' => $billing->billedCostUsd,
                'currency' => 'USD',
                'occurred_at' => now(),
                'metadata' => [
                    'usage_cost_usd' => $billing->usageCostUsd,
                    'compute_units' => $billing->computeUnits,
                    'charged_event_counts' => $billing->chargedEventCounts,
                    'billed_cost_breakdown' => $billing->breakdown,
                ],
            ],
        );

        return $billing;
    }
}
