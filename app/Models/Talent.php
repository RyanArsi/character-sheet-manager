<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Talent extends Model
{
    use HasFactory;

    // O inflector trata "talent" como invariável (plural = "talent"),
    // então fixamos o nome correto da tabela.
    protected $table = 'talents';

    protected $fillable = [
        'user_id', 'name', 'image', 'media', 'volume', 'tags',
        'chakra_cost', 'test_dice', 'damage_dice', 'actions', 'area_range', 'target', 'description', 'infos', 'hidden',
    ];

    protected $casts = [
        'tags' => 'array',
        'volume' => 'integer',
        'hidden' => 'boolean',
    ];

    /** Criador/dono do talento */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Fichas que têm este talento atribuído */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_talent')
            ->withTimestamps();
    }
}
