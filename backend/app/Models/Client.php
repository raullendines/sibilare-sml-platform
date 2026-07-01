<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'industry',
        'default_locale',
        'timezone',
    ];

    public function branding(): HasOne
    {
        return $this->hasOne(ClientBranding::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(ClientUser::class);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(ClientConfiguration::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(ClientSubscription::class);
    }

    public function planItems(): HasMany
    {
        return $this->hasMany(ClientPlanItem::class);
    }

    public function costBudgets(): HasMany
    {
        return $this->hasMany(CostBudget::class);
    }

    public function platforms(): HasMany
    {
        return $this->hasMany(ClientPlatform::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function extractionConfigs(): HasMany
    {
        return $this->hasMany(ExtractionConfig::class);
    }

    public function extractionRuns(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function usageLedger(): HasMany
    {
        return $this->hasMany(UsageLedger::class);
    }

    public function dataAvailability(): HasMany
    {
        return $this->hasMany(ClientDataAvailability::class);
    }

    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class);
    }
}
