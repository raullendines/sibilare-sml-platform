<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dashboard extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'client_id',
        'project_id',
        'name',
        'slug',
        'description',
        'status',
        'is_default',
        'grid_columns',
        'layout_mode',
        'current_version_number',
        'created_by_user_id',
        'updated_by_user_id',
        'published_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'grid_columns' => 'integer',
        'layout_mode' => 'string',
        'current_version_number' => 'integer',
        'published_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'updated_by_user_id');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class)->orderBy('sort_order');
    }

    public function filters(): HasMany
    {
        return $this->hasMany(DashboardFilter::class)->orderBy('sort_order');
    }

    public function userPreferences(): HasMany
    {
        return $this->hasMany(DashboardUserPreference::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DashboardVersion::class)->orderByDesc('version_number');
    }
}
