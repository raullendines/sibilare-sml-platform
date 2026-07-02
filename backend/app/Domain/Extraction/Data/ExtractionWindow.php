<?php

namespace App\Domain\Extraction\Data;

use Carbon\CarbonImmutable;

final readonly class ExtractionWindow
{
    public function __construct(
        public string $frequency,
        public int $overlapDays,
        public CarbonImmutable $scheduledFor,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public CarbonImmutable $fetchStart,
        public CarbonImmutable $fetchEnd,
    ) {}
}
