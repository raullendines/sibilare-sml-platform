<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSubscription extends Model
{
    protected $primaryKey = 'client_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $fillable = [
        'client_id',
        'default_data_frequency',
        'default_retroactive_days',
        'default_max_posts_per_period',
        'competitor_analysis_enabled',
        'ai_chatbot_enabled',
        'ai_pattern_detection_enabled',
        'client_presentations_enabled',
        'monthly_message_limit',
        'monthly_price',
        'price_basis',
        'billing_cycle',
        'contract_start',
        'contract_end',
    ];

    protected $casts = [
        'default_retroactive_days' => 'integer',
        'default_max_posts_per_period' => 'integer',
        'competitor_analysis_enabled' => 'boolean',
        'ai_chatbot_enabled' => 'boolean',
        'ai_pattern_detection_enabled' => 'boolean',
        'client_presentations_enabled' => 'boolean',
        'monthly_message_limit' => 'integer',
        'monthly_price' => 'decimal:2',
        'contract_start' => 'date',
        'contract_end' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
