<?php

namespace App\Console\Commands;

use App\Jobs\ClaimAndLaunchExtraction;
use Illuminate\Console\Command;

class DispatchExtractionWorkersCommand extends Command
{
    protected $signature = 'extractions:dispatch {--count=}';

    protected $description = 'Dispatch queue jobs that atomically claim pending extraction work';

    public function handle(): int
    {
        $count = (int) ($this->option('count') ?: config('services.apify.max_concurrent_launches', 20));
        $count = max(1, min($count, 100));

        for ($index = 0; $index < $count; $index++) {
            ClaimAndLaunchExtraction::dispatch();
        }

        $this->info("Dispatched {$count} extraction launcher(s).");

        return self::SUCCESS;
    }
}
