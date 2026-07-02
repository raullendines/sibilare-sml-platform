<?php

namespace App\Jobs;

use App\Domain\Extraction\Actions\FinalizeExtractionRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeApifyExtraction implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 180;

    public function __construct(public readonly string $runId)
    {
        $this->onQueue('extractions');
    }

    public function handle(FinalizeExtractionRun $finalize): void
    {
        $finalize->handle($this->runId);
    }
}
