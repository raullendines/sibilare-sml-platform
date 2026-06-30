<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandPlatformVolumeEstimate extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'client_id',
        'brand_id',
        'platform_id',
        'estimated_monthly_mentions',
        'source',
        'confidence',
        'suggested_tier',
        'suggested_multiplier',
        'notes',
    ];

    protected $casts = [
        'estimated_monthly_mentions' => 'integer',
        'confidence' => 'decimal:3',
        'suggested_multiplier' => 'decimal:4',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
