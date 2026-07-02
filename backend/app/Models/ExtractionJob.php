<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExtractionJob extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'extraction_config_id',
        'client_id',
        'scheduled_for',
        'frequency_type',
        'overlap_days',
        'period_start',
        'period_end',
        'fetch_start',
        'fetch_end',
        'reserved_cost_usd',
        'status',
        'locked_at',
        'locked_by',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'overlap_days' => 'integer',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'fetch_start' => 'datetime',
        'fetch_end' => 'datetime',
        'reserved_cost_usd' => 'decimal:4',
        'locked_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'completed_at' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function extractionConfig(): BelongsTo
    {
        return $this->belongsTo(ExtractionConfig::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(ExtractionRun::class)
            ->orderByDesc('attempt_number')
            ->orderByDesc('started_at');
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(ExtractionBatch::class, 'extraction_batch_jobs')
            ->withPivot(['client_id', 'created_at']);
    }
}
