<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Action extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'tags', 'test_dice', 'damage_dice', 'description', 'hidden',
    ];

    protected $casts = [
        'tags'   => 'array',
        'hidden' => 'boolean',
    ];

    /** Criador/dono da ação */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Fichas que têm esta ação atribuída */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_action')
            ->withTimestamps();
    }
}
