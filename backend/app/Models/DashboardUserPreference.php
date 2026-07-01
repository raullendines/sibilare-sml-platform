<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardUserPreference extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'client_id',
        'dashboard_id',
        'client_user_id',
        'filter_values',
        'last_opened_at',
    ];

    protected $casts = [
        'filter_values' => 'array',
        'last_opened_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class);
    }
}
