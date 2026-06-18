<?php

namespace App\Livewire;

use App\Models\Character;
use App\Models\Equipment;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class EquipmentPanel extends Component
{
    use WithFileUploads;

    /** Locais de carga possíveis */
    public const LOCATIONS = ['mochila', 'carregando', 'pergaminhos'];

    #[Locked]
    public int $characterId;

    /** Modo do painel: list | browse | form */
    public string $view = 'list';

    /** Tags ativas no filtro da biblioteca */
    public array $activeFilters = [];

    // ---- Limites de espaço por local (definidos pelo jogador, persistidos na ficha) ----
    public int $limitMochila = 0;
    public int $limitCarregando = 0;
    public int $limitPergaminhos = 0;

    // ---- Caixinha de significado da tag ----
    public ?string $tagName = null;
    public string $tagDescription = '';
    public bool $editingTag = false;

    // ---- Import/Export ----
    public $importFile;
    public ?string $importMessage = null;

    // ---- Formulário (criador/editor) ----
    public ?int $editingId = null;
    public string $name = '';
    public string $tagsInput = '';
    public string $test_dice = '';
    public string $damage_dice = '';
    public $space = '';
    public string $description = '';
    public string $infos = '';
    public $image;               // upload novo
    public ?string $imagePath = null;
    public $media;               // upload de áudio/vídeo novo
    public ?string $mediaPath = null;
    public int $volume = 100;

    public function mount(int $characterId): void
    {
        $character = Character::findOrFail($characterId);
        abort_unless($character->canBeManagedBy(auth()->user()), 403);

        $this->characterId = $characterId;
        $this->limitMochila     = (int) $character->space_mochila;
        $this->limitCarregando  = (int) $character->space_carregando;
        $this->limitPergaminhos = (int) $character->space_pergaminhos;
    }

    protected function character(): Character
    {
        return Character::findOrFail($this->characterId);
    }

    /** Persiste os limites de espaço quando o jogador os altera. */
    public function updated($property): void
    {
        if (in_array($property, ['limitMochila', 'limitCarregando', 'limitPergaminhos'], true)) {
            $this->character()->update([
                'space_mochila'     => max(0, (int) $this->limitMochila),
                'space_carregando'  => max(0, (int) $this->limitCarregando),
                'space_pergaminhos' => max(0, (int) $this->limitPergaminhos),
            ]);
        }
    }

    /** Usuários cujos equipamentos formam a biblioteca disponível para esta ficha:
     *  o dono da ficha + todos os membros das campanhas em que a ficha está. */
    protected function poolUserIds(Character $character): Collection
    {
        $ids = collect([$character->user_id, auth()->id()]);

        $campaignIds = $character->campaigns()->pluck('campaigns.id');
        if ($campaignIds->isNotEmpty()) {
            $memberIds = User::whereHas('campaigns', fn ($q) => $q->whereIn('campaigns.id', $campaignIds))
                ->pluck('id');
            $ids = $ids->merge($memberIds);
        }

        return $ids->unique()->values();
    }

    // ---------- Navegação ----------
    public function startCreate(): void
    {
        $this->resetForm();
        $this->view = 'form';
    }

    public function startEdit(int $equipmentId): void
    {
        $equipment = Equipment::where('user_id', auth()->id())->findOrFail($equipmentId);

        $this->editingId   = $equipment->id;
        $this->name        = $equipment->name;
        $this->tagsInput   = implode(', ', $equipment->tags ?? []);
        $this->test_dice   = $equipment->test_dice ?? '';
        $this->damage_dice = $equipment->damage_dice ?? '';
        $this->space       = $equipment->space ?? '';
        $this->description = $equipment->description ?? '';
        $this->infos       = $equipment->infos ?? '';
        $this->imagePath   = $equipment->image;
        $this->image       = null;
        $this->mediaPath   = $equipment->media;
        $this->media       = null;
        $this->volume      = $equipment->volume ?? 100;
        $this->view        = 'form';
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->view = 'list';
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'tagsInput', 'test_dice', 'damage_dice', 'space',
            'description', 'infos', 'image', 'imagePath', 'media', 'mediaPath', 'volume',
        ]);
    }

    // ---------- Persistência ----------
    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:120',
            'tagsInput'   => 'nullable|string|max:255',
            'test_dice'   => 'nullable|string|max:120',
            'damage_dice' => 'nullable|string|max:120',
            'space'       => 'nullable|integer|min:0|max:100000',
            'description' => 'nullable|string|max:5000',
            'infos'       => 'nullable|string|max:5000',
            'image'       => 'nullable|image|max:2048',
            'media'       => 'nullable|file|mimes:mp3,wav,ogg,m4a,aac,mpga,mp4,webm,mov,ogv|max:20480',
            'volume'      => 'integer|min:0|max:100',
        ]);

        $tags = collect(explode(',', $this->tagsInput))
            ->map(fn ($t) => trim(str_replace(['"', "'"], '', $t)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Garante que cada tag exista no dicionário (descrição preenchida depois)
        foreach ($tags as $t) {
            Tag::firstOrCreate(['name' => $t]);
        }

        $data = [
            'name'        => $this->name,
            'tags'        => $tags,
            'test_dice'   => $this->test_dice ?: null,
            'damage_dice' => $this->damage_dice ?: null,
            'space'       => ($this->space === '' || $this->space === null) ? null : (int) $this->space,
            'description' => $this->description ?: null,
            'infos'       => $this->infos ?: null,
            'volume'      => $this->volume,
        ];

        if ($this->image) {
            $data['image'] = $this->image->store('equipments', 'public');
        }

        if ($this->media) {
            $data['media'] = $this->media->store('equipments/media', 'public');
        }

        if ($this->editingId) {
            // Só o criador edita o próprio equipamento
            $equipment = Equipment::where('user_id', auth()->id())->findOrFail($this->editingId);
            $equipment->update($data);
        } else {
            $equipment = auth()->user()->equipments()->create($data);
            // Ao criar pela ficha, já atribui o equipamento a ela
            $this->character()->equipments()->syncWithoutDetaching([$equipment->id]);
        }

        $this->resetForm();
        $this->view = 'list';
    }

    public function deleteEquipment(int $equipmentId): void
    {
        Equipment::where('user_id', auth()->id())->findOrFail($equipmentId)->delete();
        $this->view = 'list';
    }

    // ---------- Atribuição à ficha ----------
    public function assign(int $equipmentId): void
    {
        $character = $this->character();
        $pool = $this->poolUserIds($character);

        $equipment = Equipment::whereIn('user_id', $pool)->findOrFail($equipmentId);
        $character->equipments()->syncWithoutDetaching([$equipment->id]);
    }

    public function unassign(int $equipmentId): void
    {
        $this->character()->equipments()->detach($equipmentId);
    }

    /** Move um equipamento da ficha para outro local de carga. */
    public function setLocation(int $equipmentId, string $location): void
    {
        if (! in_array($location, self::LOCATIONS, true)) {
            return;
        }

        $character = $this->character();
        if ($character->equipments()->where('equipments.id', $equipmentId)->exists()) {
            $character->equipments()->updateExistingPivot($equipmentId, ['location' => $location]);
        }
    }

    // ---------- Filtro ----------
    public function toggleFilter(string $tag): void
    {
        if (in_array($tag, $this->activeFilters, true)) {
            $this->activeFilters = array_values(array_diff($this->activeFilters, [$tag]));
        } else {
            $this->activeFilters[] = $tag;
        }
    }

    // ---------- Significado da tag ----------
    public function openTag(string $name): void
    {
        $tag = Tag::firstOrCreate(['name' => $name]);

        $this->tagName        = $tag->name;
        $this->tagDescription = $tag->description ?? '';
        $this->editingTag     = false;
    }

    public function editTag(): void
    {
        $this->editingTag = true;
    }

    public function saveTag(): void
    {
        if ($this->tagName === null) {
            return;
        }

        $this->validate(['tagDescription' => 'nullable|string|max:1000']);

        Tag::where('name', $this->tagName)
            ->update(['description' => $this->tagDescription ?: null]);

        $this->editingTag = false;
    }

    public function closeTag(): void
    {
        $this->tagName        = null;
        $this->tagDescription = '';
        $this->editingTag     = false;
    }

    // ---------- Export / Import ----------

    /** Exporta para JSON os equipamentos visíveis na biblioteca (próprios + dos membros das campanhas). */
    public function exportEquipments()
    {
        $character = $this->character();

        $equipments = Equipment::whereIn('user_id', $this->poolUserIds($character))
            ->with('user')
            ->orderBy('name')
            ->get();

        $tagNames = $equipments->flatMap(fn ($e) => $e->tags ?? [])->unique()->values();
        $tags = Tag::whereIn('name', $tagNames)
            ->get(['name', 'description'])
            ->map(fn ($t) => ['name' => $t->name, 'description' => $t->description])
            ->all();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'equipments' => $equipments->map(fn ($e) => [
                'name'        => $e->name,
                'tags'        => $e->tags ?? [],
                'test_dice'   => $e->test_dice,
                'damage_dice' => $e->damage_dice,
                'space'       => $e->space,
                'description' => $e->description,
                'infos'       => $e->infos,
                'image'       => $e->image,
                'media'       => $e->media,
                'volume'      => $e->volume,
                'created_by'  => $e->user->name ?? null,
            ])->all(),
            'tags' => $tags,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(
            fn () => print ($json),
            'equipamentos-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    /** Importa equipamentos de um arquivo JSON, registrando-os na biblioteca do usuário. */
    public function importEquipments(): void
    {
        $this->validate(['importFile' => 'required|file|max:5120']);

        $data = json_decode(file_get_contents($this->importFile->getRealPath()), true);

        if (! is_array($data) || ! isset($data['equipments']) || ! is_array($data['equipments'])) {
            $this->addError('importFile', 'Arquivo inválido: não contém uma lista de equipamentos.');

            return;
        }

        // Traz o dicionário de tags (preenche descrição só se ainda estiver vazia)
        foreach (($data['tags'] ?? []) as $t) {
            if (empty($t['name'])) {
                continue;
            }
            $tag = Tag::firstOrCreate(['name' => $t['name']]);
            if (empty($tag->description) && ! empty($t['description'])) {
                $tag->update(['description' => $t['description']]);
            }
        }

        // Nomes que o usuário já tem, para não duplicar
        $existing = $this->normalizedOwnNames();
        $imported = 0;

        foreach ($data['equipments'] as $e) {
            if (empty($e['name']) || in_array(mb_strtolower($e['name']), $existing, true)) {
                continue;
            }

            $tags = collect((array) ($e['tags'] ?? []))
                ->map(fn ($x) => trim(str_replace(['"', "'"], '', (string) $x)))
                ->filter()->unique()->values()->all();

            foreach ($tags as $x) {
                Tag::firstOrCreate(['name' => $x]);
            }

            auth()->user()->equipments()->create([
                'name'        => mb_substr((string) $e['name'], 0, 120),
                'tags'        => $tags,
                'test_dice'   => $e['test_dice'] ?? null,
                'damage_dice' => $e['damage_dice'] ?? null,
                'space'       => isset($e['space']) ? (int) $e['space'] : null,
                'description' => $e['description'] ?? null,
                'infos'       => $e['infos'] ?? null,
                'image'       => $e['image'] ?? null,
                'media'       => $e['media'] ?? null,
                'volume'      => $e['volume'] ?? 100,
            ]);

            $existing[] = mb_strtolower($e['name']);
            $imported++;
        }

        $this->importFile = null;
        $this->importMessage = $imported > 0
            ? "{$imported} equipamento(s) importado(s)."
            : 'Nenhum equipamento novo para importar (já estavam na sua biblioteca).';
    }

    /** @return array<int,string> nomes (lowercase) dos equipamentos do próprio usuário */
    protected function normalizedOwnNames(): array
    {
        return auth()->user()->equipments()
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower($n))
            ->all();
    }

    public function render()
    {
        $character = $this->character();

        $assigned = $character->equipments()->with('user')->orderBy('name')->get();

        $available = Equipment::whereIn('user_id', $this->poolUserIds($character))
            ->with('user')
            ->orderBy('name')
            ->get();

        // Todas as tags existentes na biblioteca (para o filtro)
        $allTags = $available->flatMap(fn ($e) => $e->tags ?? [])->unique()->sort()->values();

        // Aplica filtro: equipamento precisa conter todas as tags selecionadas
        if ($this->activeFilters) {
            $available = $available->filter(
                fn ($e) => count(array_intersect($this->activeFilters, $e->tags ?? [])) === count($this->activeFilters)
            )->values();
        }

        // Agrupa os itens da ficha por local de carga, com espaço usado e limite
        $limits = [
            'mochila'     => ['label' => 'Mochila',     'model' => 'limitMochila',     'limit' => $this->limitMochila],
            'carregando'  => ['label' => 'Carregando',  'model' => 'limitCarregando',  'limit' => $this->limitCarregando],
            'pergaminhos' => ['label' => 'Pergaminhos', 'model' => 'limitPergaminhos', 'limit' => $this->limitPergaminhos],
        ];

        $grouped = [];
        foreach (self::LOCATIONS as $loc) {
            $items = $assigned->filter(fn ($e) => ($e->pivot->location ?? 'mochila') === $loc)->values();
            $grouped[$loc] = [
                'items' => $items,
                'used'  => $items->sum(fn ($e) => $e->space ?? 0),
            ];
        }

        return view('livewire.equipment-panel', [
            'available'   => $available,
            'assignedIds' => $assigned->pluck('id')->all(),
            'allTags'     => $allTags,
            'authId'      => auth()->id(),
            'locations'   => self::LOCATIONS,
            'limits'      => $limits,
            'grouped'     => $grouped,
        ]);
    }
}
