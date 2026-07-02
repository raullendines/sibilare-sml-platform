<?php

namespace App\Domain\Extraction\Actions;

use App\Domain\Extraction\ApifyAgentStrategyFactory;
use App\Domain\Extraction\ApifyHttpClient;
use App\Domain\Extraction\Exceptions\ExtractionBudgetExceeded;
use App\Models\ApifyAgent;
use App\Models\ExtractionJob;
use App\Models\ExtractionRun;
use Illuminate\Support\Facades\DB;
use Throwable;

class LaunchExtractionRun
{
    public function __construct(
        private readonly ApifyHttpClient $apify,
        private readonly ApifyAgentStrategyFactory $strategies,
        private readonly ReserveExtractionBudget $reserveBudget,
    ) {}

    public function handle(ExtractionJob $job): ?ExtractionRun
    {
        $job->load(['extractionConfig.client.subscription', 'extractionConfig.project', 'extractionConfig.platform']);
        $config = $job->extractionConfig;

        if (! $config || ! $config->is_active || $config->client?->status !== 'active') {
            $job->update(['status' => 'skipped', 'reserved_cost_usd' => 0, 'completed_at' => now()]);

            return null;
        }

        if (! $this->platformEnabled($job->client_id, $config->platform_id)) {
            $job->update(['status' => 'skipped', 'reserved_cost_usd' => 0, 'completed_at' => now()]);

            return null;
        }

        if ($config->project_id !== null
            && ($config->project === null || ! $config->project->brands()->whereKey($config->brand_id)->exists())) {
            $job->update(['status' => 'failed', 'reserved_cost_usd' => 0, 'completed_at' => now()]);

            return null;
        }

        $usedAgentIds = $job->runs()->pluck('agent_id')->filter()->all();
        $agents = ApifyAgent::query()
            ->where('platform_id', $config->platform_id)
            ->where('is_active', true)
            ->when($usedAgentIds !== [], fn ($query) => $query->whereNotIn('id', $usedAgentIds))
            ->orderByDesc('is_primary')
            ->orderBy('priority')
            ->get();

        if ($agents->isEmpty()) {
            $job->update(['status' => 'failed', 'reserved_cost_usd' => 0, 'completed_at' => now()]);

            return null;
        }

        $job->update(['status' => 'launching']);
        $previousAgentId = $job->runs()->latest('attempt_number')->value('agent_id');

        foreach ($agents as $agent) {
            $estimate = (float) ($agent->cost_per_run_estimate ?? 0)
                + (min($config->max_posts_per_run, $agent->max_items_limit ?? PHP_INT_MAX) * (float) ($agent->cost_per_item_estimate ?? 0));

            try {
                $this->reserveBudget->handle($job, $config, max($estimate, (float) $job->reserved_cost_usd));
            } catch (ExtractionBudgetExceeded $exception) {
                $job->update(['status' => 'skipped', 'reserved_cost_usd' => 0, 'completed_at' => now()]);

                return null;
            }

            $strategy = $this->strategies->forPlatform($config->platform->code);
            $input = $strategy->buildInput($config, $job, $agent);
            $attemptNumber = ((int) $job->runs()->max('attempt_number')) + 1;
            $run = $job->runs()->create([
                'extraction_config_id' => $config->id,
                'client_id' => $config->client_id,
                'brand_id' => $config->brand_id,
                'platform_id' => $config->platform_id,
                'agent_id' => $agent->id,
                'fallback_from_agent_id' => $previousAgentId,
                'fallback_reason' => $previousAgentId ? 'previous_actor_failed' : null,
                'attempt_number' => $attemptNumber,
                'frequency_type' => $job->frequency_type,
                'period_start' => $job->period_start,
                'period_end' => $job->period_end,
                'fetch_start' => $job->fetch_start,
                'fetch_end' => $job->fetch_end,
                'status' => 'starting',
                'input_payload' => $input,
                'posts_requested' => min($config->max_posts_per_run, $agent->max_items_limit ?? PHP_INT_MAX),
                'currency' => 'USD',
                'pricing_snapshot' => $this->pricingSnapshot($agent),
                'started_at' => now(),
            ]);

            try {
                $external = $this->apify->startRun($agent->actor_id, $input, $agent->supports_webhook);
                $run->update([
                    'external_run_id' => $external['id'],
                    'dataset_id' => $external['defaultDatasetId'] ?? null,
                    'status' => 'waiting_provider',
                ]);
                $agent->update(['last_used_at' => now()]);
                $job->update(['status' => 'waiting_provider']);

                return $run->refresh();
            } catch (Throwable $exception) {
                report($exception);
                $run->update([
                    'status' => 'failed',
                    'error_code' => 'apify_start_failed',
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'finished_at' => now(),
                ]);
                $previousAgentId = $agent->id;
            }
        }

        $job->update(['status' => 'failed', 'reserved_cost_usd' => 0, 'completed_at' => now()]);

        return null;
    }

    private function platformEnabled(string $clientId, string $platformId): bool
    {
        return DB::table('client_platforms')
            ->where('client_id', $clientId)
            ->where('platform_id', $platformId)
            ->where('enabled', true)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function pricingSnapshot(ApifyAgent $agent): array
    {
        return [
            'actor_id' => $agent->actor_id,
            'billing_model' => $agent->billing_model,
            'pricing_unit' => $agent->pricing_unit,
            'cost_per_run_estimate' => $agent->cost_per_run_estimate,
            'cost_per_item_estimate' => $agent->cost_per_item_estimate,
            'pricing_details' => $agent->pricing_details,
        ];
    }
}
