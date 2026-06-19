<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id', 'name', 'description', 'invite_token', 'initiative',
    ];

    protected $casts = [
        'initiative' => 'array',
    ];

    /** Estado inicial vazio do rastreador de iniciativa. */
    public static function emptyInitiative(): array
    {
        return [
            'entries'    => [],   // [{id, name, roll, is_npc, character_id, user_id}]
            'current_id' => null, // id da entrada cujo turno é o atual (bolinha vermelha)
            'round'      => 1,
            'conditions' => [],   // [{id, name, target_id, turns_left}]
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign) {
            if (empty($campaign->invite_token)) {
                $campaign->invite_token = static::generateInviteToken();
            }
        });
    }

    public static function generateInviteToken(): string
    {
        do {
            $token = Str::random(32);
        } while (static::where('invite_token', $token)->exists());

        return $token;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'campaign_characters')
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(CampaignEvent::class);
    }
}
