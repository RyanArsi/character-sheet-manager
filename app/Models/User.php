<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class)->orderByDesc('updated_at');
    }

    /**
     * Jutsus criados pelo usuário (biblioteca pessoal).
     */
    public function jutsus(): HasMany
    {
        return $this->hasMany(Jutsu::class)->orderByDesc('updated_at');
    }

    /**
     * Talentos criados pelo usuário (biblioteca pessoal).
     */
    public function talents(): HasMany
    {
        return $this->hasMany(Talent::class)->orderByDesc('updated_at');
    }

    /**
     * Equipamentos criados pelo usuário (biblioteca pessoal).
     */
    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class)->orderByDesc('updated_at');
    }

    /**
     * Campanhas das quais o usuário é mestre (dono).
     */
    public function ownedCampaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'owner_id')->orderByDesc('updated_at');
    }

    /**
     * Campanhas das quais o usuário participa (incluindo as que é dono).
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
