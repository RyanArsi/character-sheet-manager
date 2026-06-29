<?php

namespace App\Livewire;

use App\Events\CampaignEventBroadcast;
use App\Events\CampaignMediaBroadcast;
use App\Events\CampaignInitiativeUpdated;
use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Character;
use App\Models\CharacterMode;
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

    // Campos numéricos editáveis ficam SEM tipo estrito: ao apagar o input,
    // o Livewire recebe "" e um `public int` lançaria PropertyNotFoundException.
    // O valor vazio é normalizado para 0 em updated() (e o nível, para no mínimo 1).
    public $level = 1;
    public int $xp = 0;

    // Guarda o nível anterior para detectar quando o personagem sobe de nível
    public int $previousLevel = 1;

    public $hp_current = 20;
    public $hp_max = 20;
    public $chakra_current = 20;
    public $chakra_max = 20;

    // Classe de Armadura (CA) — número estático no escudo.
    public $defense = 0;

    // Pontos de treinamento (pt) — texto curto, máx. 12 caracteres.
    #[Rule('nullable|string|max:12')]
    public ?string $pt = '';

    // Sem tipo estrito: ao apagar o input o Livewire recebe "" e um `public int`
    // lançaria TypeError. O vazio é normalizado para 0 em updated() (zeroable).
    public $forca = 0;
    public $agilidade = 0;
    public $constituicao = 0;
    public $inteligencia = 0;
    public $sabedoria = 0;
    public $carisma = 0;

    public $ninjutsu = 0;
    public $genjutsu = 0;
    public $taijutsu = 0;

    public array $skills = [];

    /** Modos (conjuntos de modificadores ativáveis). */
    public array $modes = [];

    public $newAvatar;
    public ?string $avatarPath = null;

    // Campanhas em que esta ficha está — opções para compartilhar eventos de dados
    public array $campaignOptions = [];

    // URL de retorno (tela de origem, ex.: campanha + aba) para o botão "voltar".
    #[Locked]
    public ?string $returnUrl = null;

    public function mount(Character $character): void
    {
        abort_unless($character->canBeManagedBy(auth()->user()), 403);

        // Captura a tela de origem (?from=) uma vez, só se for do próprio site.
        $from = request('from');
        if (is_string($from) && \Illuminate\Support\Str::startsWith($from, url('/'))) {
            $this->returnUrl = $from;
        }

        $this->characterId = $character->id;

        $this->campaignOptions = Campaign::whereIn('id', $character->relatedCampaignIds())
            ->orderBy('name')
            ->get(['id', 'name', 'owner_id'])
            ->map(fn ($c) => [
                'id'        => $c->id,
                'name'      => $c->name,
                'is_master' => $c->owner_id === auth()->id(),
            ])
            ->toArray();
        $this->fill($character->only([
            'name', 'cla', 'level', 'xp', 'pt',
            'hp_current', 'hp_max', 'chakra_current', 'chakra_max', 'defense',
            'forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
            'ninjutsu', 'genjutsu', 'taijutsu',
        ]));
        $this->previousLevel = $this->level;
        $this->avatarPath = $character->avatar;

        $this->skills = $character->skills()
            ->orderBy('id')
            ->get(['id', 'name', 'attribute', 'category', 'value', 'trained', 'training_level'])
            ->map(fn ($s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'attribute'      => $s->attribute,
                'category'       => $s->category,
                'value'          => $s->value,
                'trained'        => $s->trained,
                'training_level' => $s->training_level,
            ])
            ->toArray();

        $this->modes = $character->modes()->orderBy('id')->get()
            ->map(fn ($m) => $this->mapMode($m))
            ->toArray();
    }

    /** Campos de modificador de um modo (coluna => rótulo). */
    public const MODE_MODS = [
        'mod_dados'          => 'Dados',
        'mod_pericias'       => 'Perícias',
        'mod_especializacao' => 'Especialização',
        'mod_atributos'      => 'Atributos',
        'mod_chakra_atual'   => 'Chakra atual',
        'mod_chakra_max'     => 'Chakra máx',
        'mod_vida_atual'     => 'Vida atual',
        'mod_vida_max'       => 'Vida máx',
        'mod_ca'             => 'CA',
        'mod_resistencias'   => 'Resistências',
        'mod_combate'        => 'Combate',
    ];

    public const MODE_ATTRS = ['forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma'];
    public const MODE_SPEC  = ['ninjutsu', 'genjutsu', 'taijutsu'];

    /** Esqueleto dos modificadores individuais (tudo zerado) com base nas perícias atuais. */
    protected function blankIndividual(): array
    {
        $skills = [];
        foreach ($this->skills as $s) {
            $skills[$s['id']] = 0;
        }

        return [
            'attrs'  => array_fill_keys(self::MODE_ATTRS, 0),
            'spec'   => array_fill_keys(self::MODE_SPEC, 0),
            'skills' => $skills,
        ];
    }

    /** Converte um CharacterMode no array usado pelo componente. */
    protected function mapMode(CharacterMode $m): array
    {
        $data = [
            'id'     => $m->id,
            'title'  => $m->title,
            'active' => (bool) $m->active,
        ];
        foreach (array_keys(self::MODE_MODS) as $col) {
            $data[$col] = (int) $m->{$col};
        }

        // Individuais: parte do esqueleto e sobrepõe o que está salvo.
        $skeleton = $this->blankIndividual();
        $stored   = $m->individual ?? [];

        $data['individual'] = [
            'attrs'  => array_merge($skeleton['attrs'], array_map('intval', $stored['attrs'] ?? [])),
            'spec'   => array_merge($skeleton['spec'], array_map('intval', $stored['spec'] ?? [])),
            'skills' => $skeleton['skills'],
        ];
        foreach (($stored['skills'] ?? []) as $sid => $v) {
            if (array_key_exists($sid, $data['individual']['skills'])) {
                $data['individual']['skills'][$sid] = (int) $v;
            }
        }

        return $data;
    }

    // Chamado pelo Alpine após cada round-trip — normaliza campos numéricos vazios
    // para 0 e sincroniza o localStorage com o estado do servidor.
    public function updated($property = null, $value = null): void
    {
        $this->normalizeNumeric($property);

        $this->dispatch('sync-storage', state: $this->currentState());
    }

    /** Apagar um input numérico envia "" — vale 0 (vida, chakra e valores de perícia). */
    protected function normalizeNumeric(?string $property): void
    {
        $zeroable = ['hp_current', 'hp_max', 'chakra_current', 'chakra_max', 'defense',
                     'forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
                     'ninjutsu', 'genjutsu', 'taijutsu'];

        if (in_array($property, $zeroable, true)) {
            $this->{$property} = is_numeric($this->{$property}) ? (int) $this->{$property} : 0;

            return;
        }

        // Valor de uma perícia (skills.N.value)
        if ($property !== null && preg_match('/^skills\.(\d+)\.value$/', $property, $m)) {
            $i = (int) $m[1];
            if (isset($this->skills[$i])) {
                $v = $this->skills[$i]['value'];
                $this->skills[$i]['value'] = is_numeric($v) ? (int) $v : 0;
            }
        }

        // Modificador de um modo (modes.N.mod_*)
        if ($property !== null && preg_match('/^modes\.(\d+)\.(mod_\w+)$/', $property, $m)) {
            $i = (int) $m[1];
            $col = $m[2];
            if (isset($this->modes[$i][$col])) {
                $v = $this->modes[$i][$col];
                $this->modes[$i][$col] = is_numeric($v) ? (int) $v : 0;
            }
        }
    }

    // Restaura estado vindo do localStorage (chamado pelo Alpine no init)
    public function restoreFromSession(array $data): void
    {
        $fields = ['name', 'cla', 'level', 'pt', 'hp_current', 'hp_max', 'chakra_current', 'chakra_max', 'defense',
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
                        $this->skills[$i]['attribute']      = $incoming['attribute'] ?? $skill['attribute'];
                        break;
                    }
                }
            }
        }

        if (! empty($data['modes'])) {
            foreach ($data['modes'] as $incoming) {
                foreach ($this->modes as $i => $mode) {
                    if ($mode['id'] === ($incoming['id'] ?? null)) {
                        $this->modes[$i]['title']  = $incoming['title'] ?? $mode['title'];
                        $this->modes[$i]['active'] = (bool) ($incoming['active'] ?? $mode['active']);
                        foreach (array_keys(self::MODE_MODS) as $col) {
                            $this->modes[$i][$col] = (int) ($incoming[$col] ?? $mode[$col]);
                        }
                        if (isset($incoming['individual'])) {
                            $this->modes[$i]['individual'] = $incoming['individual'];
                        }
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
        $this->persist();
        $this->dispatch('saved');
    }

    /** Grava ficha + perícias + modos no banco (sem validação/feedback). */
    protected function persist(): void
    {
        $character = Character::find($this->characterId);

        $character->update([
            'name'           => $this->name,
            'cla'            => $this->cla,
            'level'          => $this->level,
            'xp'             => $this->xp,
            'pt'             => $this->pt,
            'hp_current'     => $this->hp_current,
            'hp_max'         => $this->hp_max,
            'chakra_current' => $this->chakra_current,
            'chakra_max'     => $this->chakra_max,
            'defense'        => (int) $this->defense,
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
                    'attribute'      => $skill['attribute'] ?? null,
                ]);
        }

        foreach ($this->modes as $mode) {
            $data = [
                'title'  => $mode['title'] ?? 'Modo',
                'active' => (bool) ($mode['active'] ?? false),
            ];
            foreach (array_keys(self::MODE_MODS) as $col) {
                $data[$col] = (int) ($mode[$col] ?? 0);
            }
            $ind = $mode['individual'] ?? [];
            $data['individual'] = [
                'attrs'  => array_map('intval', $ind['attrs'] ?? []),
                'spec'   => array_map('intval', $ind['spec'] ?? []),
                'skills' => array_map('intval', $ind['skills'] ?? []),
            ];
            $character->modes()->where('id', $mode['id'])->update($data);
        }
    }

    // ---------- Modos ----------
    public function addMode(): void
    {
        $character = Character::find($this->characterId);
        $mode = $character->modes()->create(['title' => 'Novo modo']);
        $this->modes[] = $this->mapMode($mode);
    }

    public function removeMode(int $index): void
    {
        if (! isset($this->modes[$index])) {
            return;
        }

        $mode = $this->modes[$index];

        // Se estiver ativo, reverte os modificadores antes de excluir.
        if (! empty($mode['active'])) {
            $this->applyModeDeltas($mode, -1);
        }

        Character::find($this->characterId)->modes()->where('id', $mode['id'])->delete();
        array_splice($this->modes, $index, 1);

        $this->persist();
    }

    /** Liga/desliga um modo: aplica (+1) ou reverte (-1) os modificadores. */
    public function toggleMode(int $index): void
    {
        if (! isset($this->modes[$index])) {
            return;
        }

        $wasActive = ! empty($this->modes[$index]['active']);
        $this->applyModeDeltas($this->modes[$index], $wasActive ? -1 : 1);
        $this->modes[$index]['active'] = ! $wasActive;

        $this->persist();
    }

    /** Soma (sign=+1) ou subtrai (sign=-1) os modificadores de um modo nas stats da ficha. */
    protected function applyModeDeltas(array $mode, int $sign): void
    {
        $d   = fn (string $col) => (int) ($mode[$col] ?? 0) * $sign;
        $ind = $mode['individual'] ?? [];
        $ia  = fn (string $k) => (int) ($ind['attrs'][$k] ?? 0) * $sign;
        $is  = fn (string $k) => (int) ($ind['spec'][$k] ?? 0) * $sign;
        $ik  = fn ($id) => (int) ($ind['skills'][$id] ?? 0) * $sign;

        foreach (self::MODE_ATTRS as $a) {
            $this->{$a} = (int) $this->{$a} + $d('mod_atributos') + $ia($a);
        }
        foreach (self::MODE_SPEC as $a) {
            $this->{$a} = (int) $this->{$a} + $d('mod_especializacao') + $is($a);
        }

        $this->chakra_current = (int) $this->chakra_current + $d('mod_chakra_atual');
        $this->chakra_max     = (int) $this->chakra_max + $d('mod_chakra_max');
        $this->hp_current     = (int) $this->hp_current + $d('mod_vida_atual');
        $this->hp_max         = (int) $this->hp_max + $d('mod_vida_max');
        $this->defense        = (int) $this->defense + $d('mod_ca');

        foreach ($this->skills as $i => $s) {
            $general = match ($s['category'] ?? 'pericia') {
                'resistencia' => $d('mod_resistencias'),
                'combate'     => $d('mod_combate'),
                default       => $d('mod_pericias'),
            };
            $delta = $general + $ik($s['id']);
            if ($delta !== 0) {
                $this->skills[$i]['value'] = (int) $s['value'] + $delta;
            }
        }

        // 'mod_dados' não tem campo base — é somado na rolagem (front).
    }

    public function adjustAttr(string $field, int $delta): void
    {
        $allowed = ['forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
                    'ninjutsu', 'genjutsu', 'taijutsu'];

        if (! in_array($field, $allowed)) {
            return;
        }

        // Permite valores negativos (penalidades) — sem piso em 0.
        $this->$field = (int) $this->$field + $delta;
    }

    public function cycleTraining(int $index): void
    {
        if (! isset($this->skills[$index])) return;

        $current = $this->skills[$index]['training_level'] ?? 0;
        $this->skills[$index]['training_level'] = ($current + 1) % 6;
        $this->skills[$index]['trained'] = $this->skills[$index]['training_level'] > 0;
    }

    /** Atributos que governam a rolagem de uma perícia (abreviações, como nos padrões). */
    public const SKILL_ATTRIBUTES = [
        'for', 'agi', 'con', 'int', 'sab', 'car', 'nin', 'gen', 'tai',
    ];

    /** Troca o atributo padrão de rolagem de uma perícia (menu de contexto). */
    public function setSkillAttribute(int $index, string $attribute): void
    {
        if (! isset($this->skills[$index]) || ! in_array($attribute, self::SKILL_ATTRIBUTES, true)) {
            return;
        }

        $this->skills[$index]['attribute'] = $attribute;
    }

    public function adjustHp(int $delta): void
    {
        // Permite ultrapassar o máximo (ex.: vida temporária); apenas não desce de 0.
        $this->hp_current = max(0, $this->hp_current + $delta);
    }

    public function adjustChakra(int $delta): void
    {
        // Permite ultrapassar o máximo; apenas não desce de 0.
        $this->chakra_current = max(0, $this->chakra_current + $delta);
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
            'pt'             => $this->pt,
            'hp_current'     => $this->hp_current,
            'hp_max'         => $this->hp_max,
            'chakra_current' => $this->chakra_current,
            'chakra_max'     => $this->chakra_max,
            'defense'        => $this->defense,
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
            'modes'          => $this->modes,
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
        abort_unless($character->isInCampaign($campaignId), 403);

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

    /**
     * Pede que toda a campanha reproduza uma mídia (som/vídeo de um jutsu, etc.).
     * Transmite apenas a URL pública — o arquivo não trafega pelo WebSocket.
     * Não persiste: é só um gatilho de reprodução.
     */
    public function shareMedia(int $campaignId, string $url, int $volume = 100): void
    {
        $character = Character::find($this->characterId);
        abort_unless($character && $character->canBeManagedBy(auth()->user()), 403);
        abort_unless($character->isInCampaign($campaignId), 403);

        $url = trim($url);
        if ($url === '') {
            return;
        }

        broadcast(new CampaignMediaBroadcast(
            $campaignId,
            mb_substr($url, 0, 2048),
            max(0, min(100, $volume)),
        ));
    }

    // =====================================================================
    //  Iniciativa — estado compartilhado por campanha (rastreador de turnos)
    // =====================================================================

    /** Carrega a campanha garantindo que esta ficha pertence a ela. */
    private function campaignForInitiative(int $campaignId): Campaign
    {
        $character = Character::find($this->characterId);
        abort_unless($character && $character->canBeManagedBy(auth()->user()), 403);
        abort_unless($character->isInCampaign($campaignId), 403);

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
