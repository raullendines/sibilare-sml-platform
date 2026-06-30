<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDataAvailability extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'brand_id',
        'platform_id',
        'data_starts_at',
        'historical_backfill_available',
        'coverage_note',
    ];

    protected $casts = [
        'data_starts_at' => 'datetime',
        'historical_backfill_available' => 'boolean',
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
