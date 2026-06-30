<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingProject extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'status',
        'owner_user_id',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'owner_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class);
    }
}
