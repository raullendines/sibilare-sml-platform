<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApifyAgent extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'platform_id',
        'name',
        'actor_id',
        'is_primary',
        'priority',
        'cost_per_run_estimate',
        'cost_per_item_estimate',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'priority' => 'integer',
        'cost_per_run_estimate' => 'decimal:4',
        'cost_per_item_estimate' => 'decimal:6',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function extractionRuns(): HasMany
    {
        return $this->hasMany(ExtractionRun::class, 'agent_id');
    }

    public function fallbackRuns(): HasMany
    {
        return $this->hasMany(ExtractionRun::class, 'fallback_from_agent_id');
    }
}
