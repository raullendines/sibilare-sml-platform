<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTask extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'onboarding_project_id',
        'task_code',
        'title',
        'status',
        'assigned_to_user_id',
        'due_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(OnboardingProject::class, 'onboarding_project_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'assigned_to_user_id');
    }
}
