<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidgetTemplate extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'widget_type',
        'metric_code',
        'default_title',
        'default_visualization_type',
        'default_config',
        'default_width',
        'default_height',
        'min_width',
        'min_height',
        'is_active',
    ];

    protected $casts = [
        'default_config' => 'array',
        'default_width' => 'integer',
        'default_height' => 'integer',
        'min_width' => 'integer',
        'min_height' => 'integer',
        'is_active' => 'boolean',
    ];

    public function metric(): BelongsTo
    {
        return $this->belongsTo(MetricDefinition::class, 'metric_code', 'code');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class);
    }
}
