<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtractionBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalJobs = max((int) $this->total_jobs, 0);
        $terminalJobs = (int) $this->completed_jobs + (int) $this->failed_jobs + (int) $this->skipped_jobs;

        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'requested_by_client_user_id' => $this->requested_by_client_user_id,
            'status' => $this->status,
            'progress_percent' => $totalJobs === 0 ? 100 : (int) round(($terminalJobs / $totalJobs) * 100),
            'summary' => [
                'total_jobs' => $this->total_jobs,
                'pending_jobs' => $this->pending_jobs,
                'active_jobs' => $this->active_jobs,
                'completed_jobs' => $this->completed_jobs,
                'failed_jobs' => $this->failed_jobs,
                'skipped_jobs' => $this->skipped_jobs,
                'reserved_cost_usd' => $this->reserved_cost_usd,
                'usage_cost_usd' => $this->usage_cost_usd,
                'billed_cost_usd' => $this->billed_cost_usd,
            ],
            'project' => ProjectResource::make($this->whenLoaded('project')),
            'jobs' => ExtractionBatchJobResource::collection($this->whenLoaded('jobs')),
            'launched_at' => $this->launched_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
