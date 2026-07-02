<?php

namespace App\Domain\Extraction\Actions;

use App\Domain\Extraction\Exceptions\ExtractionBudgetExceeded;
use App\Models\CostBudget;
use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;
use App\Models\UsageLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReserveExtractionBudget
{
    public function handle(ExtractionJob $job, ExtractionConfig $config, float $estimateUsd): float
    {
        $estimateUsd = round(max(0, $estimateUsd), 4);

        if ($config->cost_limit_per_run !== null && $estimateUsd > (float) $config->cost_limit_per_run) {
            throw new ExtractionBudgetExceeded('The estimated run cost exceeds the extraction configuration limit.');
        }

        return DB::transaction(function () use ($job, $config, $estimateUsd): float {
            $budgets = CostBudget::query()
                ->where('client_id', $job->client_id)
                ->where('is_active', true)
                ->where('currency', 'USD')
                ->where(function ($query) use ($config): void {
                    $query->where('scope_type', 'client')
                        ->orWhere(fn ($nested) => $nested->where('scope_type', 'feature')->where('feature_code', 'apify'))
                        ->orWhere(fn ($nested) => $nested->where('scope_type', 'brand')->where('brand_id', $config->brand_id))
                        ->orWhere(fn ($nested) => $nested->where('scope_type', 'platform')->where('platform_id', $config->platform_id))
                        ->orWhere(fn ($nested) => $nested
                            ->where('scope_type', 'brand_platform')
                            ->where('brand_id', $config->brand_id)
                            ->where('platform_id', $config->platform_id));
                })
                ->lockForUpdate()
                ->get();

            foreach ($budgets as $budget) {
                if ($budget->hard_limit_amount === null) {
                    continue;
                }

                $periodStart = $this->periodStart($budget->period);
                $spentQuery = UsageLedger::query()
                    ->where('client_id', $job->client_id)
                    ->where('usage_type', 'apify_run')
                    ->where('currency', 'USD')
                    ->where('occurred_at', '>=', $periodStart);
                $reservedQuery = ExtractionJob::query()
                    ->join('extraction_configs as budget_configs', 'budget_configs.id', '=', 'extraction_jobs.extraction_config_id')
                    ->where('extraction_jobs.client_id', $job->client_id)
                    ->where('extraction_jobs.id', '<>', $job->id)
                    ->whereIn('status', ['locked', 'launching', 'waiting_provider', 'finalizing'])
                    ->select('extraction_jobs.*');

                if (in_array($budget->scope_type, ['brand', 'brand_platform'], true)) {
                    $spentQuery->where('brand_id', $budget->brand_id);
                    $reservedQuery->where('budget_configs.brand_id', $budget->brand_id);
                }

                if (in_array($budget->scope_type, ['platform', 'brand_platform'], true)) {
                    $spentQuery->where('platform_id', $budget->platform_id);
                    $reservedQuery->where('budget_configs.platform_id', $budget->platform_id);
                }

                $spent = (float) $spentQuery->sum('cost_amount');
                $reserved = (float) $reservedQuery->sum('extraction_jobs.reserved_cost_usd');

                if ($spent + $reserved + $estimateUsd > (float) $budget->hard_limit_amount) {
                    throw new ExtractionBudgetExceeded("The {$budget->period} Apify budget would be exceeded.");
                }
            }

            $job->reserved_cost_usd = $estimateUsd;
            $job->save();

            return $estimateUsd;
        }, 3);
    }

    private function periodStart(string $period): CarbonImmutable
    {
        $now = CarbonImmutable::now('UTC');

        return match ($period) {
            'daily' => $now->startOfDay(),
            'weekly' => $now->startOfWeek(),
            default => $now->startOfMonth(),
        };
    }
}
