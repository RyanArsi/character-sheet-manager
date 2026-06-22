<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $table = 'character_notes';

    protected $fillable = [
        'character_id', 'title', 'body',
    ];

    /** Ficha a que esta nota pertence */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
