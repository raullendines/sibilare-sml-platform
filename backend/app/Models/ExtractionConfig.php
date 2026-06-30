<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractionConfig extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'client_id',
        'brand_id',
        'platform_id',
        'search_query',
        'frequency',
        'retroactive_days',
        'max_posts_per_run',
        'selection_strategy',
        'cost_limit_per_run',
        'is_active',
    ];

    protected $casts = [
        'retroactive_days' => 'integer',
        'max_posts_per_run' => 'integer',
        'cost_limit_per_run' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ExtractionJob::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }
}
