<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Jutsu extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'image', 'tags',
        'chakra_cost', 'actions', 'area_range', 'target', 'description', 'infos',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    /** Criador/dono do jutsu */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Fichas que têm este jutsu atribuído */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_jutsu')
            ->withTimestamps();
    }
}
