<?php

namespace App\Domain\Extraction\Actions;

use App\Models\ExtractionBatch;
use Illuminate\Support\Facades\DB;

class SummarizeExtractionBatch
{
    public function handle(ExtractionBatch $batch): ExtractionBatch
    {
        $jobIds = $batch->jobs()->pluck('extraction_jobs.id');

        $counts = [
            'total_jobs' => $jobIds->count(),
            'pending_jobs' => 0,
            'active_jobs' => 0,
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'skipped_jobs' => 0,
        ];
        $reserved = 0.0;
        $usage = 0.0;
        $billed = 0.0;

        if ($jobIds->isNotEmpty()) {
            $jobRows = DB::table('extraction_jobs')
                ->select(['status', 'reserved_cost_usd'])
                ->whereIn('id', $jobIds)
                ->get();

            foreach ($jobRows as $row) {
                $reserved += (float) ($row->reserved_cost_usd ?? 0);

                match ($row->status) {
                    'pending' => $counts['pending_jobs']++,
                    'locked', 'launching', 'waiting_provider', 'finalizing' => $counts['active_jobs']++,
                    'completed' => $counts['completed_jobs']++,
                    'failed', 'cancelled' => $counts['failed_jobs']++,
                    'skipped' => $counts['skipped_jobs']++,
                    default => null,
                };
            }

            $runTotals = DB::table('extraction_runs')
                ->whereIn('extraction_job_id', $jobIds)
                ->selectRaw('coalesce(sum(usage_cost_usd), 0) as usage_cost_usd')
                ->selectRaw('coalesce(sum(billed_cost_usd), 0) as billed_cost_usd')
                ->first();

            $usage = (float) ($runTotals?->usage_cost_usd ?? 0);
            $billed = (float) ($runTotals?->billed_cost_usd ?? 0);
        }

        $terminalJobs = $counts['completed_jobs'] + $counts['failed_jobs'] + $counts['skipped_jobs'];
        $status = match (true) {
            $counts['total_jobs'] === 0 => 'completed',
            $counts['failed_jobs'] === $counts['total_jobs'] => 'failed',
            $counts['skipped_jobs'] === $counts['total_jobs'] => 'skipped',
            $terminalJobs === $counts['total_jobs'] && $counts['failed_jobs'] > 0 => 'partial',
            $terminalJobs === $counts['total_jobs'] => 'completed',
            $counts['active_jobs'] > 0 => 'running',
            default => 'queued',
        };

        $batch->fill([
            ...$counts,
            'status' => $status,
            'reserved_cost_usd' => round($reserved, 4),
            'usage_cost_usd' => round($usage, 6),
            'billed_cost_usd' => round($billed, 6),
            'finished_at' => $terminalJobs === $counts['total_jobs'] ? now() : null,
        ]);
        $batch->save();

        return $batch->refresh();
    }
}
