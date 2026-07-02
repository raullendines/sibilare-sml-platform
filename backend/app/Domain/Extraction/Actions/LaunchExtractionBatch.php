<?php

namespace App\Domain\Extraction\Actions;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\ExtractionBatch;
use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LaunchExtractionBatch
{
    public function __construct(
        private readonly ResolveExtractionWindow $resolveExtractionWindow,
        private readonly SummarizeExtractionBatch $summarizeExtractionBatch,
    ) {}

    /**
     * @param  list<string>|null  $configIds
     */
    public function handle(
        Client $client,
        ClientUser $clientUser,
        ?Project $project = null,
        ?array $configIds = null,
    ): ExtractionBatch {
        $configs = $client->extractionConfigs()
            ->with(['client', 'project'])
            ->where('is_active', true)
            ->when($project !== null, fn ($query) => $query->where('project_id', $project->id))
            ->when(is_array($configIds) && $configIds !== [], fn ($query) => $query->whereIn('id', $configIds))
            ->orderBy('created_at')
            ->get();

        return DB::transaction(function () use ($client, $clientUser, $project, $configs): ExtractionBatch {
            $batch = ExtractionBatch::create([
                'client_id' => $client->id,
                'project_id' => $project?->id,
                'requested_by_client_user_id' => $clientUser->id,
                'status' => 'queued',
                'launched_at' => now(),
            ]);

            if ($configs->isEmpty()) {
                return $this->summarizeExtractionBatch->handle($batch);
            }

            $jobIds = [];

            foreach ($configs as $config) {
                $job = $this->createOrReuseJob($client, $config);
                $jobIds[] = $job->id;
            }

            $batch->jobs()->attach(
                collect($jobIds)
                    ->unique()
                    ->mapWithKeys(fn (string $jobId) => [$jobId => ['client_id' => $client->id]])
                    ->all()
            );

            return $this->summarizeExtractionBatch->handle($batch->fresh());
        });
    }

    private function createOrReuseJob(Client $client, ExtractionConfig $config): ExtractionJob
    {
        $timezone = $client->timezone ?: 'UTC';
        $frequency = $config->effectiveFrequency();
        $window = $this->resolveExtractionWindow->handle(
            $frequency,
            $timezone,
            CarbonImmutable::now($timezone),
        );

        /** @var ExtractionJob $job */
        $job = ExtractionJob::query()->firstOrNew([
            'extraction_config_id' => $config->id,
            'period_start' => $window->periodStart->utc(),
            'period_end' => $window->periodEnd->utc(),
        ]);

        if (! $job->exists) {
            $job->fill([
                'client_id' => $client->id,
                'scheduled_for' => now(),
                'frequency_type' => $frequency,
                'overlap_days' => 3,
                'fetch_start' => $window->fetchStart->utc(),
                'fetch_end' => $window->fetchEnd->utc(),
                'status' => 'pending',
            ]);
            $job->save();

            return $job;
        }

        if (in_array($job->status, ['failed', 'cancelled', 'skipped'], true)) {
            $job->fill([
                'scheduled_for' => now(),
                'frequency_type' => $frequency,
                'overlap_days' => 3,
                'fetch_start' => $window->fetchStart->utc(),
                'fetch_end' => $window->fetchEnd->utc(),
                'status' => 'pending',
                'locked_at' => null,
                'locked_by' => null,
                'next_retry_at' => null,
                'completed_at' => null,
                'reserved_cost_usd' => 0,
                'retry_count' => 0,
            ]);
            $job->save();
        }

        return $job;
    }
}
