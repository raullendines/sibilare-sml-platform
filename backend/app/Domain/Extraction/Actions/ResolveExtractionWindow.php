<?php

namespace App\Domain\Extraction\Actions;

use App\Domain\Extraction\Data\ExtractionWindow;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ResolveExtractionWindow
{
    public const OVERLAP_DAYS = 3;

    public const SCHEDULE_HOUR = 3;

    public function handle(string $frequency, string $timezone, ?CarbonImmutable $now = null): ExtractionWindow
    {
        if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            throw new InvalidArgumentException("Unsupported extraction frequency: {$frequency}");
        }

        $localNow = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $anchor = match ($frequency) {
            'daily' => $this->dailyAnchor($localNow),
            'weekly' => $this->weeklyAnchor($localNow),
            'monthly' => $this->monthlyAnchor($localNow),
        };

        $periodEnd = $anchor->startOfDay();
        $periodStart = match ($frequency) {
            'daily' => $periodEnd->subDay(),
            'weekly' => $periodEnd->subWeek(),
            'monthly' => $periodEnd->subMonthNoOverflow(),
        };

        return new ExtractionWindow(
            frequency: $frequency,
            overlapDays: self::OVERLAP_DAYS,
            scheduledFor: $anchor->utc(),
            periodStart: $periodStart->utc(),
            periodEnd: $periodEnd->utc(),
            fetchStart: $periodStart->subDays(self::OVERLAP_DAYS)->utc(),
            fetchEnd: $periodEnd->utc(),
        );
    }

    private function dailyAnchor(CarbonImmutable $now): CarbonImmutable
    {
        $today = $now->startOfDay()->addHours(self::SCHEDULE_HOUR);

        return $now->lessThan($today) ? $today->subDay() : $today;
    }

    private function weeklyAnchor(CarbonImmutable $now): CarbonImmutable
    {
        $thisMonday = $now->startOfWeek()->startOfDay()->addHours(self::SCHEDULE_HOUR);

        return $now->lessThan($thisMonday) ? $thisMonday->subWeek() : $thisMonday;
    }

    private function monthlyAnchor(CarbonImmutable $now): CarbonImmutable
    {
        $thisMonth = $now->startOfMonth()->startOfDay()->addHours(self::SCHEDULE_HOUR);

        return $now->lessThan($thisMonth) ? $thisMonth->subMonthNoOverflow() : $thisMonth;
    }
}
