<?php

namespace Tests\Unit;

use App\Domain\Extraction\Actions\CalculateApifyBilling;
use App\Models\ApifyAgent;
use PHPUnit\Framework\TestCase;

class CalculateApifyBillingTest extends TestCase
{
    public function test_charged_events_are_the_billed_source_of_truth(): void
    {
        $agent = new ApifyAgent([
            'cost_per_run_estimate' => 0.01,
            'cost_per_item_estimate' => 0.00025,
            'pricing_details' => [
                'event_prices' => [
                    'apify-actor-start' => 0.01,
                    'result' => 0.00025,
                ],
            ],
        ]);

        $billing = (new CalculateApifyBilling)->handle($agent, [
            'chargedEventCounts' => ['apify-actor-start' => 1, 'result' => 100],
            'usageTotalUsd' => 0.012,
            'computeUnits' => 0.004,
        ]);

        $this->assertSame(0.035, $billing->billedCostUsd);
        $this->assertSame(0.012, $billing->usageCostUsd);
        $this->assertSame(0.004, $billing->computeUnits);
        $this->assertSame(['apify-actor-start' => 0.01, 'result' => 0.025], $billing->breakdown);
    }

    public function test_missing_charged_events_falls_back_to_the_pricing_estimate(): void
    {
        $agent = new ApifyAgent([
            'cost_per_run_estimate' => 0.5,
            'cost_per_item_estimate' => 0.1,
        ]);

        $billing = (new CalculateApifyBilling)->handle($agent, [
            'stats' => ['itemsOutputted' => 3],
            'usageTotalUsd' => 0.2,
        ]);

        $this->assertSame(0.8, $billing->billedCostUsd);
        $this->assertSame(['estimated' => 0.8], $billing->breakdown);
    }
}
