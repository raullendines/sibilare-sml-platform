<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'client_id',
        'dashboard_id',
        'widget_template_id',
        'widget_type',
        'visualization_type',
        'metric_code',
        'title',
        'description',
        'grid_x',
        'grid_y',
        'grid_width',
        'grid_height',
        'min_width',
        'min_height',
        'sort_order',
        'config',
        'filters',
        'is_visible',
    ];

    protected $casts = [
        'grid_x' => 'integer',
        'grid_y' => 'integer',
        'grid_width' => 'integer',
        'grid_height' => 'integer',
        'min_width' => 'integer',
        'min_height' => 'integer',
        'sort_order' => 'integer',
        'config' => 'array',
        'filters' => 'array',
        'is_visible' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WidgetTemplate::class, 'widget_template_id');
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(MetricDefinition::class, 'metric_code', 'code');
    }
}
