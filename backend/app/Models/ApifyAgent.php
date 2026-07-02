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
        'task_id',
        'task_name',
        'is_primary',
        'priority',
        'cost_per_run_estimate',
        'cost_per_item_estimate',
        'billing_model',
        'pricing_unit',
        'pricing_details',
        'input_schema',
        'output_schema',
        'supports_webhook',
        'actor_options',
        'max_items_limit',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'priority' => 'integer',
        'cost_per_run_estimate' => 'decimal:4',
        'cost_per_item_estimate' => 'decimal:6',
        'pricing_details' => 'array',
        'input_schema' => 'array',
        'output_schema' => 'array',
        'supports_webhook' => 'boolean',
        'actor_options' => 'array',
        'max_items_limit' => 'integer',
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
