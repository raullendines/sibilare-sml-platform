<?php

namespace Tests\Unit;

use App\Domain\Extraction\Actions\ResolveExtractionWindow;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ResolveExtractionWindowTest extends TestCase
{
    public function test_daily_window_uses_previous_calendar_day_plus_three_day_overlap(): void
    {
        $window = (new ResolveExtractionWindow)->handle(
            'daily',
            'Europe/Madrid',
            CarbonImmutable::parse('2026-07-02 10:00:00', 'Europe/Madrid'),
        );

        $this->assertSame('2026-07-01 00:00', $window->periodStart->setTimezone('Europe/Madrid')->format('Y-m-d H:i'));
        $this->assertSame('2026-07-02 00:00', $window->periodEnd->setTimezone('Europe/Madrid')->format('Y-m-d H:i'));
        $this->assertSame('2026-06-28 00:00', $window->fetchStart->setTimezone('Europe/Madrid')->format('Y-m-d H:i'));
        $this->assertSame(3, $window->overlapDays);
    }

    public function test_before_three_am_the_latest_daily_period_is_not_scheduled_early(): void
    {
        $window = (new ResolveExtractionWindow)->handle(
            'daily',
            'Europe/Madrid',
            CarbonImmutable::parse('2026-07-02 02:59:00', 'Europe/Madrid'),
        );

        $this->assertSame('2026-06-30', $window->periodStart->setTimezone('Europe/Madrid')->format('Y-m-d'));
        $this->assertSame('2026-07-01 03:00', $window->scheduledFor->setTimezone('Europe/Madrid')->format('Y-m-d H:i'));
    }

    public function test_weekly_window_is_aligned_to_iso_monday(): void
    {
        $window = (new ResolveExtractionWindow)->handle(
            'weekly',
            'Europe/Madrid',
            CarbonImmutable::parse('2026-07-02 12:00:00', 'Europe/Madrid'),
        );

        $this->assertSame('2026-06-22', $window->periodStart->setTimezone('Europe/Madrid')->format('Y-m-d'));
        $this->assertSame('2026-06-29', $window->periodEnd->setTimezone('Europe/Madrid')->format('Y-m-d'));
        $this->assertSame('2026-06-19', $window->fetchStart->setTimezone('Europe/Madrid')->format('Y-m-d'));
    }

    public function test_monthly_window_is_aligned_to_calendar_month(): void
    {
        $window = (new ResolveExtractionWindow)->handle(
            'monthly',
            'Europe/Madrid',
            CarbonImmutable::parse('2026-07-02 12:00:00', 'Europe/Madrid'),
        );

        $this->assertSame('2026-06-01', $window->periodStart->setTimezone('Europe/Madrid')->format('Y-m-d'));
        $this->assertSame('2026-07-01', $window->periodEnd->setTimezone('Europe/Madrid')->format('Y-m-d'));
        $this->assertSame('2026-05-29', $window->fetchStart->setTimezone('Europe/Madrid')->format('Y-m-d'));
    }

    public function test_dst_keeps_calendar_boundaries_in_the_client_timezone(): void
    {
        $window = (new ResolveExtractionWindow)->handle(
            'daily',
            'Europe/Madrid',
            CarbonImmutable::parse('2026-03-30 04:00:00', 'Europe/Madrid'),
        );

        $this->assertSame('2026-03-29 00:00', $window->periodStart->setTimezone('Europe/Madrid')->format('Y-m-d H:i'));
        $this->assertSame('2026-03-30 00:00', $window->periodEnd->setTimezone('Europe/Madrid')->format('Y-m-d H:i'));
    }
}
