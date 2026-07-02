<?php

namespace App\Http\Resources;

use App\Models\ExtractionRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtractionBatchJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ExtractionRun|null $latestRun */
        $latestRun = $this->latestRun;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'scheduled_for' => $this->scheduled_for?->toISOString(),
            'frequency_type' => $this->frequency_type,
            'period_start' => $this->period_start?->toISOString(),
            'period_end' => $this->period_end?->toISOString(),
            'fetch_start' => $this->fetch_start?->toISOString(),
            'fetch_end' => $this->fetch_end?->toISOString(),
            'reserved_cost_usd' => $this->reserved_cost_usd,
            'retry_count' => $this->retry_count,
            'completed_at' => $this->completed_at?->toISOString(),
            'config' => ExtractionConfigResource::make($this->whenLoaded('extractionConfig')),
            'latest_run' => $latestRun === null ? null : [
                'id' => $latestRun->id,
                'status' => $latestRun->status,
                'external_run_id' => $latestRun->external_run_id,
                'dataset_id' => $latestRun->dataset_id,
                'attempt_number' => $latestRun->attempt_number,
                'posts_requested' => $latestRun->posts_requested,
                'posts_fetched' => $latestRun->posts_fetched,
                'posts_stored' => $latestRun->posts_stored,
                'posts_discarded' => $latestRun->posts_discarded,
                'usage_cost_usd' => $latestRun->usage_cost_usd,
                'billed_cost_usd' => $latestRun->billed_cost_usd,
                'error_message' => $latestRun->error_message,
                'started_at' => $latestRun->started_at?->toISOString(),
                'finished_at' => $latestRun->finished_at?->toISOString(),
                'webhook_received_at' => $latestRun->webhook_received_at?->toISOString(),
                'agent' => $latestRun->relationLoaded('agent') ? [
                    'id' => $latestRun->agent?->id,
                    'name' => $latestRun->agent?->name,
                    'actor_id' => $latestRun->agent?->actor_id,
                ] : null,
            ],
        ];
    }
}
