<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardVersion extends Model
{
    use UsesUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'client_id',
        'dashboard_id',
        'version_number',
        'snapshot',
        'created_by_user_id',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'snapshot' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'created_by_user_id');
    }
}
