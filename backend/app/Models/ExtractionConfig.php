<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ExtractionConfig extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'client_id',
        'project_id',
        'brand_id',
        'platform_id',
        'search_query',
        'frequency',
        'retroactive_days',
        'max_posts_per_run',
        'selection_strategy',
        'cost_limit_per_run',
        'is_active',
        'query_fingerprint',
    ];

    protected $casts = [
        'retroactive_days' => 'integer',
        'max_posts_per_run' => 'integer',
        'cost_limit_per_run' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $config): void {
            if (! Schema::hasColumn('extraction_configs', 'query_fingerprint')) {
                return;
            }

            $normalizedQuery = Str::lower(trim(preg_replace('/\s+/', ' ', $config->search_query) ?? $config->search_query));
            $config->query_fingerprint = hash('sha256', $normalizedQuery);
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    public function effectiveFrequency(): string
    {
        if (is_string($this->frequency) && $this->frequency !== '') {
            return $this->frequency;
        }

        $projectFrequency = $this->project?->default_data_frequency;

        if (is_string($projectFrequency) && $projectFrequency !== '') {
            return $projectFrequency;
        }

        if (Schema::hasTable('client_subscriptions')) {
            return $this->client?->subscription?->default_data_frequency ?? 'weekly';
        }

        return 'weekly';
    }
}
