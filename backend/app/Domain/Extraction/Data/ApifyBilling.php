<?php

namespace App\Domain\Extraction\Data;

final readonly class ApifyBilling
{
    /**
     * @param  array<string, int|float>  $chargedEventCounts
     * @param  array<string, float>  $breakdown
     */
    public function __construct(
        public float $billedCostUsd,
        public ?float $usageCostUsd,
        public ?float $computeUnits,
        public array $chargedEventCounts,
        public array $breakdown,
    ) {}
}
