<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetricDefinition extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'source_domain',
        'value_type',
        'default_aggregation',
        'default_visualization_type',
        'config_schema',
        'is_active',
    ];

    protected $casts = [
        'config_schema' => 'array',
        'is_active' => 'boolean',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(WidgetTemplate::class, 'metric_code', 'code');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class, 'metric_code', 'code');
    }
}
