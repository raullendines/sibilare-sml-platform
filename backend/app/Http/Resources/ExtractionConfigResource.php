<?php

namespace App\Http\Resources;

use App\Domain\Extraction\Actions\ResolveExtractionWindow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtractionConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $effectiveFrequency = $this->resource->effectiveFrequency();
        $timezone = $this->resource->client?->timezone ?? 'UTC';
        $window = app(ResolveExtractionWindow::class)->handle($effectiveFrequency, $timezone);
        $localScheduledFor = $window->scheduledFor->setTimezone($timezone);
        $nextRunAt = (match ($effectiveFrequency) {
            'daily' => $localScheduledFor->addDay(),
            'weekly' => $localScheduledFor->addWeek(),
            default => $localScheduledFor->addMonthNoOverflow(),
        })->utc();
        $nextPeriodStart = $window->periodEnd->setTimezone($timezone);
        $nextPeriodEnd = match ($effectiveFrequency) {
            'daily' => $nextPeriodStart->addDay(),
            'weekly' => $nextPeriodStart->addWeek(),
            default => $nextPeriodStart->addMonthNoOverflow(),
        };

        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'brand_id' => $this->brand_id,
            'platform_id' => $this->platform_id,
            'search_query' => $this->search_query,
            'frequency' => $this->frequency,
            'effective_frequency' => $effectiveFrequency,
            'retroactive_days' => $this->retroactive_days,
            'overlap_days' => 3,
            'next_run_at' => $nextRunAt->toISOString(),
            'next_window' => [
                'period_start' => $nextPeriodStart->utc()->toISOString(),
                'period_end' => $nextPeriodEnd->utc()->toISOString(),
                'fetch_start' => $nextPeriodStart->subDays(3)->utc()->toISOString(),
                'fetch_end' => $nextPeriodEnd->utc()->toISOString(),
            ],
            'max_posts_per_run' => $this->max_posts_per_run,
            'selection_strategy' => $this->selection_strategy,
            'cost_limit_per_run' => $this->cost_limit_per_run,
            'is_active' => $this->is_active,
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'platform' => PlatformResource::make($this->whenLoaded('platform')),
            'project' => ProjectResource::make($this->whenLoaded('project')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
