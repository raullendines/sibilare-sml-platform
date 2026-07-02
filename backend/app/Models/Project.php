<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'client_id',
        'name',
        'slug',
        'description',
        'status',
        'default_data_frequency',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'project_brands')
            ->withPivot(['client_id', 'created_at']);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'project_posts')
            ->withPivot(['client_id', 'extraction_run_id', 'created_at']);
    }

    public function extractionConfigs(): HasMany
    {
        return $this->hasMany(ExtractionConfig::class);
    }

    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class);
    }
}
