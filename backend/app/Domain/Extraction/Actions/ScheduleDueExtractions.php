<?php

namespace App\Domain\Extraction\Actions;

use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ScheduleDueExtractions
{
    public function __construct(private readonly ResolveExtractionWindow $resolveWindow) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $created = 0;

        ExtractionConfig::query()
            ->where('is_active', true)
            ->with(['client.subscription', 'project', 'platform'])
            ->whereHas('client', fn ($query) => $query->where('status', 'active'))
            ->whereHas('platform', fn ($query) => $query->where('is_active', true))
            ->chunkById(100, function ($configs) use (&$created, $now): void {
                foreach ($configs as $config) {
                    if (! $config->client || ! $this->platformEnabled($config)) {
                        continue;
                    }

                    $window = $this->resolveWindow->handle(
                        $config->effectiveFrequency(),
                        $config->client->timezone,
                        $now,
                    );

                    try {
                        $job = ExtractionJob::query()->firstOrCreate(
                            [
                                'extraction_config_id' => $config->id,
                                'period_start' => $window->periodStart,
                                'period_end' => $window->periodEnd,
                            ],
                            [
                                'client_id' => $config->client_id,
                                'scheduled_for' => $window->scheduledFor,
                                'frequency_type' => $window->frequency,
                                'overlap_days' => $window->overlapDays,
                                'fetch_start' => $window->fetchStart,
                                'fetch_end' => $window->fetchEnd,
                                'status' => 'pending',
                                'max_retries' => 3,
                            ],
                        );

                        $created += $job->wasRecentlyCreated ? 1 : 0;
                    } catch (QueryException $exception) {
                        if (! $this->isUniqueViolation($exception)) {
                            throw $exception;
                        }
                    }
                }
            });

        return $created;
    }

    private function platformEnabled(ExtractionConfig $config): bool
    {
        return DB::table('client_platforms')
            ->where('client_id', $config->client_id)
            ->where('platform_id', $config->platform_id)
            ->where('enabled', true)
            ->exists();
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
