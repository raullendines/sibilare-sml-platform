<?php

namespace App\Domain\Extraction\Actions;

use App\Domain\Extraction\Data\ApifyBilling;
use App\Models\ApifyAgent;

class CalculateApifyBilling
{
    /**
     * @param  array<string, mixed>  $runData
     */
    public function handle(ApifyAgent $agent, array $runData): ApifyBilling
    {
        $counts = collect($runData['chargedEventCounts'] ?? [])
            ->filter(fn ($value, $key) => is_string($key) && is_numeric($value) && (float) $value >= 0)
            ->map(fn ($value) => (float) $value)
            ->all();
        $eventPrices = collect($agent->pricing_details['event_prices'] ?? [])
            ->filter(fn ($value, $key) => is_string($key) && is_numeric($value))
            ->map(fn ($value) => (float) $value)
            ->all();
        $breakdown = [];

        foreach ($counts as $event => $count) {
            $price = $eventPrices[$event]
                ?? ($event === 'apify-actor-start' ? (float) ($agent->cost_per_run_estimate ?? 0) : null)
                ?? ($agent->pricing_unit === $event ? (float) ($agent->cost_per_item_estimate ?? 0) : 0);
            $breakdown[$event] = round($count * $price, 6);
        }

        $billedCost = array_sum($breakdown);

        if ($counts === []) {
            $itemCount = (float) ($runData['stats']['itemsOutputted'] ?? $runData['itemCount'] ?? 0);
            $billedCost = (float) ($agent->cost_per_run_estimate ?? 0)
                + ($itemCount * (float) ($agent->cost_per_item_estimate ?? 0));
            $breakdown = ['estimated' => round($billedCost, 6)];
        }

        return new ApifyBilling(
            billedCostUsd: round($billedCost, 6),
            usageCostUsd: isset($runData['usageTotalUsd']) ? (float) $runData['usageTotalUsd'] : null,
            computeUnits: isset($runData['computeUnits']) ? (float) $runData['computeUnits'] : null,
            chargedEventCounts: $counts,
            breakdown: $breakdown,
        );
    }
}
