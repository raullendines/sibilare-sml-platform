<?php

namespace App\Jobs;

use App\Domain\Extraction\Actions\ClaimPendingExtractionJob;
use App\Domain\Extraction\Actions\LaunchExtractionRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ClaimAndLaunchExtraction implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct()
    {
        $this->onQueue('extractions');
    }

    public function handle(ClaimPendingExtractionJob $claim, LaunchExtractionRun $launch): void
    {
        $job = $claim->handle('queue-'.Str::uuid());

        if ($job !== null) {
            $launch->handle($job);
        }
    }
}
