<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientConfigurationItem extends Model
{
    use HasFactory;
    use UsesUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'client_configuration_id',
        'item_type',
        'item_id',
        'change_type',
        'notes',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(ClientConfiguration::class, 'client_configuration_id');
    }
}
