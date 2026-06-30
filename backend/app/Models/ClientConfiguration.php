<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientConfiguration extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'client_id',
        'version_number',
        'status',
        'name',
        'notes',
        'created_by_user_id',
        'activated_by_user_id',
        'activated_at',
        'archived_at',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'activated_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'created_by_user_id');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'activated_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientConfigurationItem::class);
    }

    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class);
    }

    public function subproducts(): HasMany
    {
        return $this->hasMany(Subproduct::class);
    }
}
