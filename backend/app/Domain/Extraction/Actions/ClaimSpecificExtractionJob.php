<?php

namespace App\Domain\Extraction\Actions;

use App\Models\ExtractionJob;
use Illuminate\Support\Facades\DB;

class ClaimSpecificExtractionJob
{
    public function handle(ExtractionJob $job, string $workerId): ?ExtractionJob
    {
        return DB::transaction(function () use ($job, $workerId): ?ExtractionJob {
            $query = ExtractionJob::query()
                ->whereKey($job->id)
                ->where('status', 'pending')
                ->where('scheduled_for', '<=', now())
                ->where(fn ($nested) => $nested->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()));

            if (DB::getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            $claimableJob = $query->first();

            if (! $claimableJob instanceof ExtractionJob) {
                return null;
            }

            $claimableJob->update([
                'status' => 'locked',
                'locked_at' => now(),
                'locked_by' => $workerId,
            ]);

            return $claimableJob->refresh();
        }, 3);
    }
}
