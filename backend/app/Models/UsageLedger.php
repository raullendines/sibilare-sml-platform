<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLedger extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    protected $table = 'usage_ledger';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'usage_type',
        'source_table',
        'source_id',
        'brand_id',
        'platform_id',
        'quantity',
        'unit',
        'cost_amount',
        'currency',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'cost_amount' => 'decimal:4',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
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
