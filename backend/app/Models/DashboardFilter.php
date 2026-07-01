<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardFilter extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'client_id',
        'dashboard_id',
        'field_code',
        'label',
        'filter_type',
        'default_value',
        'config',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'default_value' => 'array',
        'config' => 'array',
        'sort_order' => 'integer',
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
}
