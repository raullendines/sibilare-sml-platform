<?php

namespace App\Console\Commands;

use App\Domain\Extraction\Actions\ScheduleDueExtractions;
use Illuminate\Console\Command;

class ScheduleExtractionsCommand extends Command
{
    protected $signature = 'extractions:schedule';

    protected $description = 'Create idempotent extraction jobs for every due active configuration';

    public function handle(ScheduleDueExtractions $schedule): int
    {
        $count = $schedule->handle();
        $this->info("Scheduled {$count} extraction job(s).");

        return self::SUCCESS;
    }
}
