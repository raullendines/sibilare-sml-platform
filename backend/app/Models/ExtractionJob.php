<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractionJob extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'extraction_config_id',
        'client_id',
        'scheduled_for',
        'status',
        'locked_at',
        'locked_by',
        'retry_count',
        'max_retries',
        'next_retry_at',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'locked_at' => 'datetime',
        'next_retry_at' => 'datetime',
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
}
