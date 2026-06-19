<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignEvent extends Model
{
    protected $fillable = [
        'campaign_id', 'user_id', 'character_id', 'actor', 'message', 'detail',
    ];

    protected $casts = [
        'detail' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
