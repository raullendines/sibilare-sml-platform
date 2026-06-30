<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subproduct extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'client_id',
        'client_configuration_id',
        'theme_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(ClientConfiguration::class, 'client_configuration_id');
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }
}
