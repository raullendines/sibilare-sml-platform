<?php

namespace App\Domain\Extraction\Actions;

use App\Models\ExtractionJob;
use Illuminate\Support\Facades\DB;

class ClaimPendingExtractionJob
{
    public function handle(string $workerId): ?ExtractionJob
    {
        return DB::transaction(function () use ($workerId): ?ExtractionJob {
            $query = ExtractionJob::query()
                ->where('status', 'pending')
                ->where('scheduled_for', '<=', now())
                ->where(fn ($nested) => $nested->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
                ->orderBy('scheduled_for');

            if (DB::getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            $job = $query->first();

            if (! $job instanceof ExtractionJob) {
                return null;
            }

            $job->update([
                'status' => 'locked',
                'locked_at' => now(),
                'locked_by' => $workerId,
            ]);

            return $job->refresh();
        }, 3);
    }
}
