<?php

namespace App\Console\Commands;

use App\Jobs\FinalizeApifyExtraction;
use App\Models\ExtractionRun;
use Illuminate\Console\Command;

class WatchApifyRunsCommand extends Command
{
    protected $signature = 'extractions:watchdog {--limit=100}';

    protected $description = 'Poll asynchronous Apify runs whose webhook may be delayed or missing';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $threshold = now()->subMinutes((int) config('services.apify.watchdog_after_minutes', 5));
        $runIds = ExtractionRun::query()
            ->where('status', 'waiting_provider')
            ->where('started_at', '<=', $threshold)
            ->oldest('started_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($runIds as $runId) {
            $claimed = ExtractionRun::query()
                ->whereKey($runId)
                ->where('status', 'waiting_provider')
                ->update(['status' => 'finalizing']);

            if ($claimed === 1) {
                FinalizeApifyExtraction::dispatch($runId);
            }
        }

        $this->info("Queued {$runIds->count()} run(s) for watchdog inspection.");

        return self::SUCCESS;
    }
}
