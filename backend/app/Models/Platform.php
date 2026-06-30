<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function clientPlatforms(): HasMany
    {
        return $this->hasMany(ClientPlatform::class);
    }

    public function brandAliases(): HasMany
    {
        return $this->hasMany(BrandAlias::class);
    }

    public function volumeEstimates(): HasMany
    {
        return $this->hasMany(BrandPlatformVolumeEstimate::class);
    }

    public function apifyAgents(): HasMany
    {
        return $this->hasMany(ApifyAgent::class);
    }

    public function extractionConfigs(): HasMany
    {
        return $this->hasMany(ExtractionConfig::class);
    }

    public function extractionRuns(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }

    public function platformPosts(): HasMany
    {
        return $this->hasMany(PlatformPost::class);
    }
}
