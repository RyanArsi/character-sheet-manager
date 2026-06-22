<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignSheetGroup extends Model
{
    use HasFactory;

    protected $fillable = ['campaign_id', 'name'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** Fichas (NPCs) deste grupo */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class, 'sheet_group_id');
    }
}
