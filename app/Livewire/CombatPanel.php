<?php

namespace App\Livewire;

use App\Events\CampaignEventBroadcast;
use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Character;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CombatPanel extends Component
{
    #[Locked]
    public int $campaignId;

    public function mount(int $campaignId): void
    {
        $this->authorizeOwner($this->campaign($campaignId));
        $this->campaignId = $campaignId;
    }

    protected function campaign(?int $id = null): Campaign
    {
        return Campaign::findOrFail($id ?? $this->campaignId);
    }

    protected function authorizeOwner(Campaign $campaign): void
    {
        abort_unless($campaign->owner_id === auth()->id(), 403);
    }

    /** Ficha de NPC desta campanha (valida que pertence ao mestre/campanha). */
    protected function npc(int $characterId): Character
    {
        return $this->campaign()->npcSheets()->findOrFail($characterId);
    }

    // ---------- Combatentes ----------
    public function addCombatant(int $characterId): void
    {
        $this->authorizeOwner($campaign = $this->campaign());
        $this->npc($characterId); // valida

        $combat = $campaign->combat ?? [];
        if (! in_array($characterId, $combat, true)) {
            $combat[] = $characterId;
            $campaign->update(['combat' => array_values($combat)]);
        }
    }

    public function removeCombatant(int $characterId): void
    {
        $this->authorizeOwner($campaign = $this->campaign());
        $combat = array_values(array_diff($campaign->combat ?? [], [$characterId]));
        $campaign->update(['combat' => $combat]);
    }

    // ---------- Vida / Chakra (persistem na ficha; podem passar do máximo) ----------
    public function adjustHp(int $characterId, int $delta): void
    {
        $this->authorizeOwner($this->campaign());
        $c = $this->npc($characterId);
        $c->update(['hp_current' => max(0, (int) $c->hp_current + $delta)]);
    }

    public function adjustChakra(int $characterId, int $delta): void
    {
        $this->authorizeOwner($this->campaign());
        $c = $this->npc($characterId);
        $c->update(['chakra_current' => max(0, (int) $c->chakra_current + $delta)]);
    }

    public function setHp(int $characterId, $value): void
    {
        $this->authorizeOwner($this->campaign());
        $this->npc($characterId)->update(['hp_current' => max(0, (int) $value)]);
    }

    public function setChakra(int $characterId, $value): void
    {
        $this->authorizeOwner($this->campaign());
        $this->npc($characterId)->update(['chakra_current' => max(0, (int) $value)]);
    }

    // ---------- Rolagem -> feed da campanha ----------
    public function shareRoll(int $characterId, string $message, array $detail = []): void
    {
        $this->authorizeOwner($this->campaign());
        $c = $this->npc($characterId);

        $event = CampaignEvent::create([
            'campaign_id'  => $this->campaignId,
            'user_id'      => auth()->id(),
            'character_id' => $c->id,
            'actor'        => $c->name ?: 'NPC',
            'message'      => mb_substr(trim($message), 0, 255),
            'detail'       => $detail ?: null,
        ]);

        broadcast(new CampaignEventBroadcast($event));
    }

    protected function combatantData(Character $c): array
    {
        $ability = fn ($i) => [
            'name'   => $i->name,
            'test'   => $i->test_dice,
            'damage' => $i->damage_dice,
        ];

        return [
            'id'             => $c->id,
            'name'           => $c->name ?: 'Sem nome',
            'avatar'         => $c->avatar ? Storage::url($c->avatar) : null,
            'hp_current'     => (int) $c->hp_current,
            'hp_max'         => (int) $c->hp_max,
            'chakra_current' => (int) $c->chakra_current,
            'chakra_max'     => (int) $c->chakra_max,
            'attrs'          => [
                'forca' => (int) $c->forca, 'agilidade' => (int) $c->agilidade,
                'constituicao' => (int) $c->constituicao, 'inteligencia' => (int) $c->inteligencia,
                'sabedoria' => (int) $c->sabedoria, 'carisma' => (int) $c->carisma,
                'ninjutsu' => (int) $c->ninjutsu, 'genjutsu' => (int) $c->genjutsu, 'taijutsu' => (int) $c->taijutsu,
            ],
            'skills'    => $c->skills->map(fn ($s) => [
                'name' => $s->name, 'value' => (int) $s->value, 'training_level' => (int) $s->training_level,
            ])->values()->all(),
            'jutsus'    => $c->jutsus->map(fn ($i) => $ability($i) + ['chakra' => $i->chakra_cost])->values()->all(),
            'talents'   => $c->talents->map(fn ($i) => $ability($i) + ['chakra' => $i->chakra_cost])->values()->all(),
            'actions'   => $c->actions->map($ability)->values()->all(),
            'equipments' => $c->equipments->map($ability)->values()->all(),
            'tests'     => $c->tests->map($ability)->values()->all(),
        ];
    }

    public function render()
    {
        $campaign = $this->campaign();
        $ids = $campaign->combat ?? [];

        $chars = Character::whereIn('id', $ids)
            ->where('campaign_id', $campaign->id)
            ->with(['skills', 'jutsus', 'talents', 'equipments', 'actions', 'tests'])
            ->get()
            ->sortBy(fn ($c) => array_search($c->id, $ids))
            ->values();

        $combatData = $chars->map(fn ($c) => $this->combatantData($c))->all();

        $available = $campaign->npcSheets()
            ->whereNotIn('id', $ids ?: [0])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.combat-panel', [
            'combatants' => $chars,
            'combatData' => $combatData,
            'combatKey'  => implode('-', $ids),
            'available'  => $available,
            'initiative' => $campaign->initiative ?: Campaign::emptyInitiative(),
        ]);
    }
}
