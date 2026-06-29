<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterMode extends Model
{
    protected $fillable = [
        'character_id', 'title', 'active',
        'mod_dados', 'mod_pericias', 'mod_especializacao', 'mod_atributos',
        'mod_chakra_atual', 'mod_chakra_max', 'mod_vida_atual', 'mod_vida_max',
        'mod_ca', 'mod_resistencias', 'mod_combate', 'individual',
    ];

    protected $casts = [
        'active'             => 'boolean',
        'individual'         => 'array',
        'mod_dados'          => 'integer',
        'mod_pericias'       => 'integer',
        'mod_especializacao' => 'integer',
        'mod_atributos'      => 'integer',
        'mod_chakra_atual'   => 'integer',
        'mod_chakra_max'     => 'integer',
        'mod_vida_atual'     => 'integer',
        'mod_vida_max'       => 'integer',
        'mod_ca'             => 'integer',
        'mod_resistencias'   => 'integer',
        'mod_combate'        => 'integer',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
