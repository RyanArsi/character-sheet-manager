<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterTest extends Model
{
    use HasFactory;

    protected $table = 'character_tests';

    protected $fillable = [
        'character_id', 'name', 'test_dice', 'damage_dice',
    ];

    /** Ficha a que este teste pertence */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
