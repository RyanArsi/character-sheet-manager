<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipment extends Model
{
    use HasFactory;

    // "equipment" é invariável no inflector (plural = "equipment"),
    // então fixamos o nome correto da tabela.
    protected $table = 'equipments';

    protected $fillable = [
        'user_id', 'name', 'rank', 'image', 'media', 'volume', 'tags',
        'test_dice', 'damage_dice', 'space', 'description', 'infos', 'hidden',
    ];

    protected $casts = [
        'tags' => 'array',
        'volume' => 'integer',
        'space' => 'integer',
        'hidden' => 'boolean',
    ];

    /** Criador/dono do equipamento */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Fichas que têm este equipamento atribuído */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_equipment')
            ->withPivot('location')
            ->withTimestamps();
    }
}
