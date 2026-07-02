<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'client_id',
        'brand_id',
        'platform_post_id',
        'extraction_run_id',
        'matched_query',
        'match_type',
        'is_relevant_candidate',
    ];

    protected $casts = [
        'is_relevant_candidate' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function platformPost(): BelongsTo
    {
        return $this->belongsTo(PlatformPost::class);
    }

    public function extractionRun(): BelongsTo
    {
        return $this->belongsTo(ExtractionRun::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_posts')
            ->withPivot(['client_id', 'extraction_run_id', 'created_at']);
    }
}
