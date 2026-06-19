<?php

namespace App\Livewire;

use App\Events\CampaignEventBroadcast;
use App\Events\CampaignInitiativeUpdated;
use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Character;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.sheet')]
class CharacterSheet extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $characterId;

    #[Rule('required|string|max:100')]
    public string $name = '';

    #[Rule('nullable|string|max:100')]
    public ?string $cla = '';

    public int $level = 1;
    public int $xp = 0;

    // Guarda o nível anterior para detectar quando o personagem sobe de nível
    public int $previousLevel = 1;

    public int $hp_current = 20;
    public int $hp_max = 20;
    public int $chakra_current = 20;
    public int $chakra_max = 20;

    public int $forca = 0;
    public int $agilidade = 0;
    public int $constituicao = 0;
    public int $inteligencia = 0;
    public int $sabedoria = 0;
    public int $carisma = 0;

    public int $ninjutsu = 0;
    public int $genjutsu = 0;
    public int $taijutsu = 0;

    public array $skills = [];

    public $newAvatar;
    public ?string $avatarPath = null;

    // Campanhas em que esta ficha está — opções para compartilhar eventos de dados
    public array $campaignOptions = [];

    public function mount(Character $character): void
    {
        abort_unless($character->canBeManagedBy(auth()->user()), 403);

        $this->characterId = $character->id;

        $this->campaignOptions = $character->campaigns()
            ->orderBy('name')
            ->get(['campaigns.id', 'campaigns.name', 'campaigns.owner_id'])
            ->map(fn ($c) => [
                'id'        => $c->id,
                'name'      => $c->name,
                'is_master' => $c->owner_id === auth()->id(),
            ])
            ->toArray();
        $this->fill($character->only([
            'name', 'cla', 'level', 'xp',
            'hp_current', 'hp_max', 'chakra_current', 'chakra_max',
            'forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
            'ninjutsu', 'genjutsu', 'taijutsu',
        ]));
        $this->previousLevel = $this->level;
        $this->avatarPath = $character->avatar;

        $this->skills = $character->skills()
            ->orderBy('id')
            ->get(['id', 'name', 'attribute', 'value', 'trained', 'training_level'])
            ->map(fn ($s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'attribute'      => $s->attribute,
                'value'          => $s->value,
                'trained'        => $s->trained,
                'training_level' => $s->training_level,
            ])
            ->toArray();
    }

    // Chamado pelo Alpine após cada round-trip — sincroniza localStorage com estado do servidor
    public function updated(): void
    {
        $this->dispatch('sync-storage', state: $this->currentState());
    }

    // Restaura estado vindo do localStorage (chamado pelo Alpine no init)
    public function restoreFromSession(array $data): void
    {
        $fields = ['name', 'cla', 'level', 'hp_current', 'hp_max', 'chakra_current', 'chakra_max',
                   'forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
                   'ninjutsu', 'genjutsu', 'taijutsu'];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $this->$field = $data[$field];
            }
        }

        // Sincroniza o nível de referência para não disparar o alerta indevidamente
        $this->previousLevel = $this->level;

        if (! empty($data['skills'])) {
            foreach ($data['skills'] as $incoming) {
                foreach ($this->skills as $i => $skill) {
                    if ($skill['id'] === $incoming['id']) {
                        $this->skills[$i]['value']          = $incoming['value'];
                        $this->skills[$i]['trained']        = $incoming['trained'];
                        $this->skills[$i]['training_level'] = $incoming['training_level'] ?? 0;
                        break;
                    }
                }
            }
        }
    }

    // Detecta subida de nível e dispara o alerta com os ganhos
    public function updatedLevel($value): void
    {
        $value = max(1, (int) $value);
        $this->level = $value;

        if ($value > $this->previousLevel) {
            $this->dispatch('level-up',
                hp: $this->constituicao + 4,
                chakra: 'd6 + sabedoria',
            );
        }

        $this->previousLevel = $value;
    }

    public function levelUp(): void
    {
        $this->level++;
        $this->updatedLevel($this->level);
    }

    public function levelDown(): void
    {
        $this->level = max(1, $this->level - 1);
        $this->previousLevel = $this->level;
    }

    public function save(): void
    {
        $this->validate();

        $character = Character::find($this->characterId);

        $character->update([
            'name'           => $this->name,
            'cla'            => $this->cla,
            'level'          => $this->level,
            'xp'             => $this->xp,
            'hp_current'     => $this->hp_current,
            'hp_max'         => $this->hp_max,
            'chakra_current' => $this->chakra_current,
            'chakra_max'     => $this->chakra_max,
            'forca'          => $this->forca,
            'agilidade'      => $this->agilidade,
            'constituicao'   => $this->constituicao,
            'inteligencia'   => $this->inteligencia,
            'sabedoria'      => $this->sabedoria,
            'carisma'        => $this->carisma,
            'ninjutsu'       => $this->ninjutsu,
            'genjutsu'       => $this->genjutsu,
            'taijutsu'       => $this->taijutsu,
        ]);

        foreach ($this->skills as $skill) {
            $character->skills()
                ->where('id', $skill['id'])
                ->update([
                    'value'          => $skill['value'],
                    'trained'        => $skill['trained'],
                    'training_level' => $skill['training_level'] ?? 0,
                ]);
        }

        $this->dispatch('saved');
    }

    public function adjustAttr(string $field, int $delta): void
    {
        $allowed = ['forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
                    'ninjutsu', 'genjutsu', 'taijutsu'];

        if (! in_array($field, $allowed)) {
            return;
        }

        $this->$field = max(0, $this->$field + $delta);
    }

    public function cycleTraining(int $index): void
    {
        if (! isset($this->skills[$index])) return;

        $current = $this->skills[$index]['training_level'] ?? 0;
        $this->skills[$index]['training_level'] = ($current + 1) % 6;
        $this->skills[$index]['trained'] = $this->skills[$index]['training_level'] > 0;
    }

    public function adjustHp(int $delta): void
    {
        $this->hp_current = max(0, min($this->hp_max, $this->hp_current + $delta));
    }

    public function adjustChakra(int $delta): void
    {
        $this->chakra_current = max(0, min($this->chakra_max, $this->chakra_current + $delta));
    }

    public function uploadAvatar(): void
    {
        $this->validateOnly('newAvatar', ['newAvatar' => 'image|max:2048']);

        $path = $this->newAvatar->store('avatars', 'public');

        Character::find($this->characterId)->update(['avatar' => $path]);
        $this->avatarPath = $path;
        $this->newAvatar = null;
    }

    private function currentState(): array
    {
        return [
            'name'           => $this->name,
            'cla'            => $this->cla,
            'level'          => $this->level,
            'hp_current'     => $this->hp_current,
            'hp_max'         => $this->hp_max,
            'chakra_current' => $this->chakra_current,
            'chakra_max'     => $this->chakra_max,
            'forca'          => $this->forca,
            'agilidade'      => $this->agilidade,
            'constituicao'   => $this->constituicao,
            'inteligencia'   => $this->inteligencia,
            'sabedoria'      => $this->sabedoria,
            'carisma'        => $this->carisma,
            'ninjutsu'       => $this->ninjutsu,
            'genjutsu'       => $this->genjutsu,
            'taijutsu'       => $this->taijutsu,
            'skills'         => $this->skills,
        ];
    }

    /**
     * Registra e transmite um evento (rolagem/jutsu) para o feed ao vivo da campanha.
     * Só aceita campanhas em que esta ficha realmente está.
     */
    public function shareEvent(int $campaignId, string $message, array $detail = []): void
    {
        $character = Character::find($this->characterId);
        abort_unless($character && $character->canBeManagedBy(auth()->user()), 403);
        abort_unless($character->campaigns()->whereKey($campaignId)->exists(), 403);

        $event = CampaignEvent::create([
            'campaign_id'  => $campaignId,
            'user_id'      => auth()->id(),
            'character_id' => $character->id,
            'actor'        => $this->name ?: ($character->name ?? 'Ficha'),
            'message'      => mb_substr(trim($message), 0, 255),
            'detail'       => $detail ?: null,
        ]);

        broadcast(new CampaignEventBroadcast($event));
    }

    // =====================================================================
    //  Iniciativa — estado compartilhado por campanha (rastreador de turnos)
    // =====================================================================

    /** Carrega a campanha garantindo que esta ficha pertence a ela. */
    private function campaignForInitiative(int $campaignId): Campaign
    {
        $character = Character::find($this->characterId);
        abort_unless($character && $character->canBeManagedBy(auth()->user()), 403);
        abort_unless($character->campaigns()->whereKey($campaignId)->exists(), 403);

        return Campaign::findOrFail($campaignId);
    }

    private function requireMaster(Campaign $campaign): void
    {
        abort_unless($campaign->owner_id === auth()->id(), 403);
    }

    /** Ordena entradas por iniciativa (maior primeiro). */
    private function sortEntries(array $entries): array
    {
        usort($entries, fn ($a, $b) => $b['roll'] <=> $a['roll']);

        return array_values($entries);
    }

    /** Grava o estado e transmite para toda a campanha. */
    private function persistInitiative(Campaign $campaign, array $state): array
    {
        $campaign->update(['initiative' => $state]);
        broadcast(new CampaignInitiativeUpdated($campaign->id, $state));

        return $state;
    }

    public function getInitiative(int $campaignId): array
    {
        return $this->campaignForInitiative($campaignId)->initiative ?: Campaign::emptyInitiative();
    }

    /** Jogador entra na iniciativa (d20 + agilidade + mod, rolado no cliente). Re-rolar substitui. */
    public function addInitiativeEntry(int $campaignId, int $roll): array
    {
        $campaign = $this->campaignForInitiative($campaignId);
        $state = $campaign->initiative ?: Campaign::emptyInitiative();

        $state['entries'] = array_values(array_filter(
            $state['entries'],
            fn ($e) => ($e['character_id'] ?? null) !== $this->characterId
        ));

        $state['entries'][] = [
            'id'           => (string) Str::uuid(),
            'name'         => $this->name ?: 'Ficha',
            'roll'         => $roll,
            'is_npc'       => false,
            'character_id' => $this->characterId,
            'user_id'      => auth()->id(),
        ];
        $state['entries'] = $this->sortEntries($state['entries']);

        return $this->persistInitiative($campaign, $state);
    }

    /** Mestre adiciona um NPC (nome + valor de iniciativa). */
    public function addNpc(int $campaignId, string $name, int $roll): array
    {
        $campaign = $this->campaignForInitiative($campaignId);
        $this->requireMaster($campaign);

        $name = trim($name);
        $state = $campaign->initiative ?: Campaign::emptyInitiative();
        if ($name === '') {
            return $state;
        }

        $state['entries'][] = [
            'id'           => (string) Str::uuid(),
            'name'         => mb_substr($name, 0, 60),
            'roll'         => $roll,
            'is_npc'       => true,
            'character_id' => null,
            'user_id'      => null,
        ];
        $state['entries'] = $this->sortEntries($state['entries']);

        return $this->persistInitiative($campaign, $state);
    }

    /** Mestre passa o turno: avança a bolinha; ao dar a volta completa, +1 rodada. */
    public function passTurn(int $campaignId): array
    {
        $campaign = $this->campaignForInitiative($campaignId);
        $this->requireMaster($campaign);

        $state = $campaign->initiative ?: Campaign::emptyInitiative();
        $ids = array_column($state['entries'], 'id');
        if (empty($ids)) {
            return $this->persistInitiative($campaign, $state);
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

        return $this->persistInitiative($campaign, $state);
    }

    /** Decrementa condições do participante que entra em turno; remove as zeradas. */
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

    /** Remove uma entrada (mestre, ou dono da própria ficha). */
    public function removeEntry(int $campaignId, string $entryId): array
    {
        $campaign = $this->campaignForInitiative($campaignId);
        $state = $campaign->initiative ?: Campaign::emptyInitiative();

        $entry = collect($state['entries'])->firstWhere('id', $entryId);
        if (! $entry) {
            return $state;
        }

        $isMaster = $campaign->owner_id === auth()->id();
        $isOwn = ($entry['user_id'] ?? null) === auth()->id();
        abort_unless($isMaster || $isOwn, 403);

        if ($state['current_id'] === $entryId) {
            $ids = array_column($state['entries'], 'id');
            $pos = array_search($entryId, $ids, true);
            $remaining = array_values(array_filter($ids, fn ($id) => $id !== $entryId));
            $state['current_id'] = $remaining[$pos] ?? ($remaining ? $remaining[count($remaining) - 1] : null);
        }

        $state['entries'] = array_values(array_filter($state['entries'], fn ($e) => $e['id'] !== $entryId));
        $state['conditions'] = array_values(array_filter(
            $state['conditions'] ?? [],
            fn ($c) => ($c['target_id'] ?? null) !== $entryId
        ));

        return $this->persistInitiative($campaign, $state);
    }

    /** Mestre zera a iniciativa (nova batalha). */
    public function clearInitiative(int $campaignId): array
    {
        $campaign = $this->campaignForInitiative($campaignId);
        $this->requireMaster($campaign);

        return $this->persistInitiative($campaign, Campaign::emptyInitiative());
    }

    /** Qualquer membro pode adicionar uma condição a um participante (mestre ou jogador). */
    public function addCondition(int $campaignId, string $name, string $targetId, int $turns): array
    {
        $campaign = $this->campaignForInitiative($campaignId);

        $name = trim($name);
        $state = $campaign->initiative ?: Campaign::emptyInitiative();
        $target = collect($state['entries'])->firstWhere('id', $targetId);
        if ($name === '' || ! $target || $turns < 1) {
            return $state;
        }

        $name = mb_substr($name, 0, 60);
        $turns = min(99, max(1, $turns));

        $state['conditions'][] = [
            'id'         => (string) Str::uuid(),
            'name'       => $name,
            'target_id'  => $targetId,
            'turns_left' => $turns,
        ];

        // Aviso no histórico/feed da campanha
        $this->shareEvent($campaign->id, sprintf(
            'deu a condição %s em %s por %d turno(s)',
            $name, $target['name'], $turns
        ), ['kind' => 'condition', 'name' => $name, 'target' => $target['name'], 'turns' => $turns]);

        return $this->persistInitiative($campaign, $state);
    }

    public function removeCondition(int $campaignId, string $conditionId): array
    {
        $campaign = $this->campaignForInitiative($campaignId);

        $state = $campaign->initiative ?: Campaign::emptyInitiative();
        $state['conditions'] = array_values(array_filter(
            $state['conditions'] ?? [],
            fn ($c) => $c['id'] !== $conditionId
        ));

        return $this->persistInitiative($campaign, $state);
    }

    public function render()
    {
        return view('livewire.character-sheet');
    }
}
