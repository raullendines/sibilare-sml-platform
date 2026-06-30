<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformPost extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'platform_id',
        'external_id',
        'author_handle',
        'author_name',
        'content_text',
        'url',
        'posted_at',
        'language_code',
        'media_urls',
        'metrics',
        'raw_payload',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'media_urls' => 'array',
        'metrics' => 'array',
        'raw_payload' => 'array',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function clientPosts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
