<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractionRun extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'extraction_job_id',
        'extraction_config_id',
        'client_id',
        'brand_id',
        'platform_id',
        'agent_id',
        'fallback_from_agent_id',
        'fallback_reason',
        'external_run_id',
        'dataset_id',
        'attempt_number',
        'frequency_type',
        'period_start',
        'period_end',
        'fetch_start',
        'fetch_end',
        'status',
        'input_payload',
        'result_summary',
        'posts_requested',
        'posts_fetched',
        'posts_stored',
        'posts_discarded',
        'cost_amount',
        'compute_units',
        'usage_cost_usd',
        'billed_cost_usd',
        'charged_event_counts',
        'pricing_snapshot',
        'abort_reason',
        'guardrails_hit',
        'currency',
        'error_code',
        'error_message',
        'started_at',
        'finished_at',
        'webhook_received_at',
        'finalization_started_at',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'fetch_start' => 'datetime',
        'fetch_end' => 'datetime',
        'input_payload' => 'array',
        'result_summary' => 'array',
        'posts_requested' => 'integer',
        'posts_fetched' => 'integer',
        'posts_stored' => 'integer',
        'posts_discarded' => 'integer',
        'cost_amount' => 'decimal:6',
        'attempt_number' => 'integer',
        'compute_units' => 'decimal:6',
        'usage_cost_usd' => 'decimal:6',
        'billed_cost_usd' => 'decimal:6',
        'charged_event_counts' => 'array',
        'pricing_snapshot' => 'array',
        'guardrails_hit' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'webhook_received_at' => 'datetime',
        'finalization_started_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ExtractionJob::class, 'extraction_job_id');
    }

    public function extractionConfig(): BelongsTo
    {
        return $this->belongsTo(ExtractionConfig::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ApifyAgent::class, 'agent_id');
    }

    public function fallbackFromAgent(): BelongsTo
    {
        return $this->belongsTo(ApifyAgent::class, 'fallback_from_agent_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
