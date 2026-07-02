<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'client_id',
        'parent_brand_id',
        'name',
        'brand_type',
        'logo_url',
        'color',
        'website_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_brand_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_brand_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(BrandAlias::class);
    }

    public function volumeEstimates(): HasMany
    {
        return $this->hasMany(BrandPlatformVolumeEstimate::class);
    }

    public function extractionConfigs(): HasMany
    {
        return $this->hasMany(ExtractionConfig::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_brands')
            ->withPivot(['client_id', 'created_at']);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function dataAvailability(): HasMany
    {
        return $this->hasMany(ClientDataAvailability::class);
    }
}
