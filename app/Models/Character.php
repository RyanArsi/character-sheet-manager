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
        'user_id', 'campaign_id', 'sheet_group_id', 'name', 'race', 'village', 'cla', 'level', 'xp',
        'hp_current', 'hp_max', 'chakra_current', 'chakra_max', 'defense', 'pt',
        'forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
        'ninjutsu', 'genjutsu', 'taijutsu',
        'space_mochila', 'space_carregando', 'space_pergaminhos',
        'notes', 'avatar',
    ];

    protected $casts = [
        'level'          => 'integer',
        'xp'             => 'integer',
        'hp_current'     => 'integer',
        'hp_max'         => 'integer',
        'chakra_current' => 'integer',
        'chakra_max'     => 'integer',
        'defense'        => 'integer',
        'forca'          => 'integer',
        'agilidade'      => 'integer',
        'constituicao'   => 'integer',
        'inteligencia'   => 'integer',
        'sabedoria'      => 'integer',
        'carisma'        => 'integer',
        'ninjutsu'       => 'integer',
        'genjutsu'       => 'integer',
        'taijutsu'       => 'integer',
        'space_mochila'     => 'integer',
        'space_carregando'  => 'integer',
        'space_pergaminhos' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(CharacterSkill::class);
    }

    /** Modos (conjuntos de modificadores ativáveis) desta ficha. */
    public function modes(): HasMany
    {
        return $this->hasMany(CharacterMode::class);
    }

    /** Notas livres desta ficha (aba Notas). Nome evita conflito com a coluna `notes`. */
    public function noteEntries(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /** Testes salvos desta ficha (aba Testes) — rolagens rápidas de teste/dano. */
    public function tests(): HasMany
    {
        return $this->hasMany(CharacterTest::class);
    }

    /** Jutsus atribuídos a esta ficha */
    public function jutsus(): BelongsToMany
    {
        return $this->belongsToMany(Jutsu::class, 'character_jutsu')
            ->withTimestamps();
    }

    /** Talentos atribuídos a esta ficha */
    public function talents(): BelongsToMany
    {
        return $this->belongsToMany(Talent::class, 'character_talent')
            ->withTimestamps();
    }

    /** Equipamentos atribuídos a esta ficha (com o local de carga no pivot) */
    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'character_equipment')
            ->withPivot('location')
            ->withTimestamps();
    }

    /** Ações (de perícia) atribuídas a esta ficha */
    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'character_action')
            ->withTimestamps();
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_characters')
            ->withTimestamps();
    }

    /** Campanha dona desta ficha de NPC (null = ficha normal de jogador). */
    public function ownerCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /** Grupo da ficha de NPC (ex.: Vilões, NPCs). */
    public function sheetGroup(): BelongsTo
    {
        return $this->belongsTo(CampaignSheetGroup::class, 'sheet_group_id');
    }

    /** True se for ficha de NPC pertencente a uma campanha (só o mestre vê). */
    public function isNpcSheet(): bool
    {
        return $this->campaign_id !== null;
    }

    /** Campanhas a que esta ficha pertence (pivot p/ jogador; campaign_id p/ NPC do mestre). */
    public function relatedCampaignIds(): \Illuminate\Support\Collection
    {
        return $this->campaign_id
            ? collect([(int) $this->campaign_id])
            : $this->campaigns()->pluck('campaigns.id');
    }

    /** Esta ficha participa da campanha dada? */
    public function isInCampaign(int $campaignId): bool
    {
        return $this->campaign_id
            ? (int) $this->campaign_id === $campaignId
            : $this->campaigns()->whereKey($campaignId)->exists();
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

        // Ficha de NPC: só o mestre da campanha dona.
        if ($this->campaign_id) {
            return $this->ownerCampaign()->where('owner_id', $user->id)->exists();
        }

        return $this->campaigns()
            ->where('owner_id', $user->id)
            ->exists();
    }
}
