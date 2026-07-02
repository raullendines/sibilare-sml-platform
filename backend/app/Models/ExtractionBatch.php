<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExtractionBatch extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'client_id',
        'project_id',
        'requested_by_client_user_id',
        'status',
        'total_jobs',
        'pending_jobs',
        'active_jobs',
        'completed_jobs',
        'failed_jobs',
        'skipped_jobs',
        'reserved_cost_usd',
        'usage_cost_usd',
        'billed_cost_usd',
        'launched_at',
        'finished_at',
    ];

    protected $casts = [
        'total_jobs' => 'integer',
        'pending_jobs' => 'integer',
        'active_jobs' => 'integer',
        'completed_jobs' => 'integer',
        'failed_jobs' => 'integer',
        'skipped_jobs' => 'integer',
        'reserved_cost_usd' => 'decimal:4',
        'usage_cost_usd' => 'decimal:6',
        'billed_cost_usd' => 'decimal:6',
        'launched_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requestedByClientUser(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'requested_by_client_user_id');
    }

    public function jobs(): BelongsToMany
    {
        return $this->belongsToMany(ExtractionJob::class, 'extraction_batch_jobs')
            ->withPivot(['client_id', 'created_at']);
    }
}
