<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientBranding extends Model
{
    protected $table = 'client_branding';

    protected $primaryKey = 'client_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'logo_url',
        'logo_dark_url',
        'favicon_url',
        'color_primary',
        'color_secondary',
        'color_accent',
        'font_family',
        'custom_css',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
