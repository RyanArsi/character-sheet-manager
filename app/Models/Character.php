<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'race', 'village', 'level', 'xp',
        'hp_current', 'hp_max', 'chakra_current', 'chakra_max',
        'forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
        'ninjutsu', 'genjutsu', 'taijutsu',
        'notes', 'avatar',
    ];

    protected $casts = [
        'level'          => 'integer',
        'xp'             => 'integer',
        'hp_current'     => 'integer',
        'hp_max'         => 'integer',
        'chakra_current' => 'integer',
        'chakra_max'     => 'integer',
        'forca'          => 'integer',
        'agilidade'      => 'integer',
        'constituicao'   => 'integer',
        'inteligencia'   => 'integer',
        'sabedoria'      => 'integer',
        'carisma'        => 'integer',
        'ninjutsu'       => 'integer',
        'genjutsu'       => 'integer',
        'taijutsu'       => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(CharacterSkill::class);
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_characters')
            ->withTimestamps();
    }

    /**
     * Quem pode ver/editar esta ficha: o próprio dono ou o mestre
     * de alguma campanha em que a ficha está.
     */
    public function canBeManagedBy(User $user): bool
    {
        if ($user->id === $this->user_id) {
            return true;
        }

        return $this->campaigns()
            ->where('owner_id', $user->id)
            ->exists();
    }
}
