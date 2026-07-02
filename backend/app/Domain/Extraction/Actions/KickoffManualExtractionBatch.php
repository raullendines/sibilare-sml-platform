<?php

namespace App\Domain\Extraction\Actions;

use App\Models\ExtractionBatch;
use Illuminate\Support\Str;

class KickoffManualExtractionBatch
{
    public function __construct(
        private readonly ClaimSpecificExtractionJob $claimSpecificExtractionJob,
        private readonly LaunchExtractionRun $launchExtractionRun,
        private readonly SummarizeExtractionBatch $summarizeExtractionBatch,
    ) {}

    public function handle(ExtractionBatch $batch): ExtractionBatch
    {
        $batch->loadMissing('jobs');
        $workerId = 'manual-batch-'.Str::uuid();

        foreach ($batch->jobs as $job) {
            $claimedJob = $this->claimSpecificExtractionJob->handle($job, $workerId);

            if ($claimedJob === null) {
                continue;
            }

            $this->launchExtractionRun->handle($claimedJob);
        }

        return $this->summarizeExtractionBatch->handle($batch->fresh());
    }
}
