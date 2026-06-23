<?php

namespace App\Livewire;

use App\Events\CampaignEventBroadcast;
use App\Events\CampaignInitiativeUpdated;
use App\Events\CampaignMediaBroadcast;
use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Character;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    // ---------- Iniciativa: rola e entra na ordem da campanha ----------
    public function rollInitiative(int $characterId): void
    {
        $this->authorizeOwner($campaign = $this->campaign());
        $c = $this->npc($characterId);

        $die = random_int(1, 20);
        $roll = $die + (int) $c->agilidade;

        $state = $campaign->initiative ?: Campaign::emptyInitiative();
        // Substitui entrada anterior deste combatente, se houver.
        $state['entries'] = array_values(array_filter(
            $state['entries'],
            fn ($e) => ($e['character_id'] ?? null) !== $characterId
        ));
        $state['entries'][] = [
            'id'           => (string) Str::uuid(),
            'name'         => $c->name ?: 'NPC',
            'roll'         => $roll,
            'is_npc'       => true,
            'character_id' => $characterId,
            'user_id'      => auth()->id(),
        ];
        $state['entries'] = $this->sortEntries($state['entries']);

        $this->persistInit($campaign, $state);

        $this->shareRoll(
            $characterId,
            'Iniciativa → '.$roll.' (d20: '.$die.' +'.(int) $c->agilidade.' agi)',
            ['kind' => 'attr']
        );
    }

    /** Mestre passa o turno (avança a vez; ao dar a volta, +1 rodada). */
    public function passTurn(): void
    {
        $this->authorizeOwner($campaign = $this->campaign());
        $state = $campaign->initiative ?: Campaign::emptyInitiative();
        $ids = array_column($state['entries'], 'id');
        if (empty($ids)) {
            $this->persistInit($campaign, $state);

            return;
        }

        if ($state['current_id'] === null || ! in_array($state['current_id'], $ids, true)) {
            $state['current_id'] = $ids[0];
        } else {
            $next = array_search($state['current_id'], $ids, true) + 1;
            if ($next >= count($ids)) {
                $next = 0;
                $state['round'] = ($state['round'] ?? 1) + 1;
            }
            $state['current_id'] = $ids[$next];
        }

        $state['conditions'] = $this->tickConditions($state['conditions'] ?? [], $state['current_id']);
        $this->persistInit($campaign, $state);
    }

    public function clearInitiative(): void
    {
        $this->authorizeOwner($campaign = $this->campaign());
        $this->persistInit($campaign, Campaign::emptyInitiative());
    }

    public function addNpcEntry(string $name, int $roll = 10): void
    {
        $this->authorizeOwner($campaign = $this->campaign());
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $state = $campaign->initiative ?: Campaign::emptyInitiative();
        $state['entries'][] = [
            'id'           => (string) Str::uuid(),
            'name'         => mb_substr($name, 0, 60),
            'roll'         => (int) $roll,
            'is_npc'       => true,
            'character_id' => null,
            'user_id'      => auth()->id(),
        ];
        $state['entries'] = $this->sortEntries($state['entries']);
        $this->persistInit($campaign, $state);
    }

    public function removeEntry(string $entryId): void
    {
        $this->authorizeOwner($campaign = $this->campaign());
        $state = $campaign->initiative ?: Campaign::emptyInitiative();
        if (! collect($state['entries'])->firstWhere('id', $entryId)) {
            return;
        }

        if (($state['current_id'] ?? null) === $entryId) {
            $ids = array_column($state['entries'], 'id');
            $pos = array_search($entryId, $ids, true);
            $remaining = array_values(array_filter($ids, fn ($id) => $id !== $entryId));
            $state['current_id'] = $remaining[$pos] ?? ($remaining ? end($remaining) : null);
        }

        $state['entries'] = array_values(array_filter($state['entries'], fn ($e) => $e['id'] !== $entryId));
        $state['conditions'] = array_values(array_filter(
            $state['conditions'] ?? [],
            fn ($c) => ($c['target_id'] ?? null) !== $entryId
        ));
        $this->persistInit($campaign, $state);
    }

    public function addCondition(string $name, string $targetId, int $turns): void
    {
        $this->authorizeOwner($campaign = $this->campaign());
        $name = trim($name);
        $state = $campaign->initiative ?: Campaign::emptyInitiative();
        $target = collect($state['entries'])->firstWhere('id', $targetId);
        if ($name === '' || ! $target || $turns < 1) {
            return;
        }

        $name = mb_substr($name, 0, 60);
        $turns = min(99, max(1, $turns));
        $state['conditions'][] = [
            'id'         => (string) Str::uuid(),
            'name'       => $name,
            'target_id'  => $targetId,
            'turns_left' => $turns,
        ];

        $this->feedEvent($target['name'], sprintf('recebeu a condição %s por %d turno(s)', $name, $turns),
            ['kind' => 'condition', 'name' => $name, 'turns' => $turns]);
        $this->persistInit($campaign, $state);
    }

    public function removeCondition(string $conditionId): void
    {
        $this->authorizeOwner($campaign = $this->campaign());
        $state = $campaign->initiative ?: Campaign::emptyInitiative();
        $state['conditions'] = array_values(array_filter(
            $state['conditions'] ?? [],
            fn ($c) => $c['id'] !== $conditionId
        ));
        $this->persistInit($campaign, $state);
    }

    private function sortEntries(array $entries): array
    {
        usort($entries, fn ($a, $b) => $b['roll'] <=> $a['roll']);

        return array_values($entries);
    }

    private function tickConditions(array $conditions, ?string $currentId): array
    {
        if ($currentId === null) {
            return array_values($conditions);
        }

        $out = [];
        foreach ($conditions as $c) {
            if (($c['target_id'] ?? null) === $currentId) {
                $c['turns_left'] = (int) $c['turns_left'] - 1;
                if ($c['turns_left'] <= 0) {
                    continue;
                }
            }
            $out[] = $c;
        }

        return array_values($out);
    }

    private function persistInit(Campaign $campaign, array $state): void
    {
        $campaign->update(['initiative' => $state]);
        broadcast(new CampaignInitiativeUpdated($campaign->id, $state));
    }

    private function feedEvent(string $actor, string $message, array $detail = []): void
    {
        $event = CampaignEvent::create([
            'campaign_id'  => $this->campaignId,
            'user_id'      => auth()->id(),
            'character_id' => null,
            'actor'        => $actor,
            'message'      => mb_substr(trim($message), 0, 255),
            'detail'       => $detail ?: null,
        ]);
        broadcast(new CampaignEventBroadcast($event));
    }

    /** Reproduz a mídia de um jutsu para toda a campanha (só a URL trafega). */
    public function shareMedia(string $url, int $volume = 100): void
    {
        $this->authorizeOwner($this->campaign());
        $url = trim($url);
        if ($url === '') {
            return;
        }
        broadcast(new CampaignMediaBroadcast($this->campaignId, mb_substr($url, 0, 2048), max(0, min(100, $volume))));
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

        // Eventos recentes para a coluna de feed (mais novos primeiro)
        $events = $campaign->events()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($e) => [
                'id'      => $e->id,
                'actor'   => $e->actor,
                'message' => $e->message,
                'detail'  => $e->detail,
                'time'    => $e->created_at?->format('H:i:s'),
            ])
            ->values();

        return view('livewire.combat-panel', [
            'combatants' => $chars,
            'combatData' => $combatData,
            'combatKey'  => implode('-', $ids),
            'available'  => $available,
            'initiative' => $campaign->initiative ?: Campaign::emptyInitiative(),
            'events'     => $events,
        ]);
    }
}
