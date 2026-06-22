<?php

namespace App\Livewire;

use App\Models\Character;
use App\Models\Jutsu;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class JutsuPanel extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $characterId;

    /** Modo do painel: list | browse | form */
    public string $view = 'list';

    /** Tags ativas no filtro da biblioteca */
    public array $activeFilters = [];

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
    public string $chakra_cost = '';
    public string $test_dice = '';
    public string $damage_dice = '';
    public string $actions = '';
    public string $area_range = '';
    public string $target = '';
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
    }

    protected function character(): Character
    {
        return Character::findOrFail($this->characterId);
    }

    /** Usuários cujos jutsus formam a biblioteca disponível para esta ficha:
     *  o dono da ficha + todos os membros das campanhas em que a ficha está. */
    protected function poolUserIds(Character $character): Collection
    {
        $ids = collect([$character->user_id, auth()->id()]);

        $campaignIds = $character->relatedCampaignIds();
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

    public function startEdit(int $jutsuId): void
    {
        $jutsu = Jutsu::where('user_id', auth()->id())->findOrFail($jutsuId);

        $this->editingId   = $jutsu->id;
        $this->name        = $jutsu->name;
        $this->tagsInput   = implode(', ', $jutsu->tags ?? []);
        $this->chakra_cost = $jutsu->chakra_cost ?? '';
        $this->test_dice   = $jutsu->test_dice ?? '';
        $this->damage_dice = $jutsu->damage_dice ?? '';
        $this->actions     = $jutsu->actions ?? '';
        $this->area_range  = $jutsu->area_range ?? '';
        $this->target      = $jutsu->target ?? '';
        $this->description = $jutsu->description ?? '';
        $this->infos       = $jutsu->infos ?? '';
        $this->imagePath   = $jutsu->image;
        $this->image       = null;
        $this->mediaPath   = $jutsu->media;
        $this->media       = null;
        $this->volume      = $jutsu->volume ?? 100;
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
            'editingId', 'name', 'tagsInput', 'chakra_cost', 'test_dice', 'damage_dice', 'actions',
            'area_range', 'target', 'description', 'infos', 'image', 'imagePath', 'media', 'mediaPath', 'volume',
        ]);
    }

    // ---------- Persistência ----------
    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:120',
            'tagsInput'   => 'nullable|string|max:255',
            'chakra_cost' => 'nullable|string|max:60',
            'test_dice'   => 'nullable|string|max:120',
            'damage_dice' => 'nullable|string|max:120',
            'actions'     => 'nullable|string|max:60',
            'area_range'  => 'nullable|string|max:120',
            'target'      => 'nullable|string|max:120',
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
            'chakra_cost' => $this->chakra_cost ?: null,
            'test_dice'   => $this->test_dice ?: null,
            'damage_dice' => $this->damage_dice ?: null,
            'actions'     => $this->actions ?: null,
            'area_range'  => $this->area_range ?: null,
            'target'      => $this->target ?: null,
            'description' => $this->description ?: null,
            'infos'       => $this->infos ?: null,
            'volume'      => $this->volume,
        ];

        if ($this->image) {
            $data['image'] = $this->image->store('jutsus', 'public');
        }

        if ($this->media) {
            $data['media'] = $this->media->store('jutsus/media', 'public');
        }

        if ($this->editingId) {
            // Só o criador edita o próprio jutsu
            $jutsu = Jutsu::where('user_id', auth()->id())->findOrFail($this->editingId);
            $jutsu->update($data);
        } else {
            $jutsu = auth()->user()->jutsus()->create($data);
            // Ao criar pela ficha, já atribui o jutsu a ela
            $this->character()->jutsus()->syncWithoutDetaching([$jutsu->id]);
        }

        $this->resetForm();
        $this->view = 'list';
    }

    public function deleteJutsu(int $jutsuId): void
    {
        Jutsu::where('user_id', auth()->id())->findOrFail($jutsuId)->delete();
        $this->view = 'list';
    }

    // ---------- Atribuição à ficha ----------
    public function assign(int $jutsuId): void
    {
        $character = $this->character();
        $pool = $this->poolUserIds($character);

        $jutsu = Jutsu::whereIn('user_id', $pool)->findOrFail($jutsuId);
        $character->jutsus()->syncWithoutDetaching([$jutsu->id]);
    }

    public function unassign(int $jutsuId): void
    {
        $this->character()->jutsus()->detach($jutsuId);
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

    /** Exporta para JSON os jutsus visíveis na biblioteca (próprios + dos membros das campanhas). */
    public function exportJutsus()
    {
        $character = $this->character();

        $jutsus = Jutsu::whereIn('user_id', $this->poolUserIds($character))
            ->with('user')
            ->orderBy('name')
            ->get();

        $tagNames = $jutsus->flatMap(fn ($j) => $j->tags ?? [])->unique()->values();
        $tags = Tag::whereIn('name', $tagNames)
            ->get(['name', 'description'])
            ->map(fn ($t) => ['name' => $t->name, 'description' => $t->description])
            ->all();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'jutsus' => $jutsus->map(fn ($j) => [
                'name'        => $j->name,
                'tags'        => $j->tags ?? [],
                'chakra_cost' => $j->chakra_cost,
                'test_dice'   => $j->test_dice,
                'damage_dice' => $j->damage_dice,
                'actions'     => $j->actions,
                'area_range'  => $j->area_range,
                'target'      => $j->target,
                'description' => $j->description,
                'infos'       => $j->infos,
                'image'       => $j->image,
                'media'       => $j->media,
                'volume'      => $j->volume,
                'created_by'  => $j->user->name ?? null,
            ])->all(),
            'tags' => $tags,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(
            fn () => print ($json),
            'jutsus-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    /** Importa jutsus de um arquivo JSON, registrando-os na biblioteca do usuário. */
    public function importJutsus(): void
    {
        $this->validate(['importFile' => 'required|file|max:5120']);

        $data = json_decode(file_get_contents($this->importFile->getRealPath()), true);

        if (! is_array($data) || ! isset($data['jutsus']) || ! is_array($data['jutsus'])) {
            $this->addError('importFile', 'Arquivo inválido: não contém uma lista de jutsus.');

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

        foreach ($data['jutsus'] as $j) {
            if (empty($j['name']) || in_array(mb_strtolower($j['name']), $existing, true)) {
                continue;
            }

            $tags = collect((array) ($j['tags'] ?? []))
                ->map(fn ($t) => trim(str_replace(['"', "'"], '', (string) $t)))
                ->filter()->unique()->values()->all();

            foreach ($tags as $t) {
                Tag::firstOrCreate(['name' => $t]);
            }

            auth()->user()->jutsus()->create([
                'name'        => mb_substr((string) $j['name'], 0, 120),
                'tags'        => $tags,
                'chakra_cost' => $j['chakra_cost'] ?? null,
                'test_dice'   => $j['test_dice'] ?? null,
                'damage_dice' => $j['damage_dice'] ?? null,
                'actions'     => $j['actions'] ?? null,
                'area_range'  => $j['area_range'] ?? null,
                'target'      => $j['target'] ?? null,
                'description' => $j['description'] ?? null,
                'infos'       => $j['infos'] ?? null,
                'image'       => $j['image'] ?? null,
                'media'       => $j['media'] ?? null,
                'volume'      => $j['volume'] ?? 100,
            ]);

            $existing[] = mb_strtolower($j['name']);
            $imported++;
        }

        $this->importFile = null;
        $this->importMessage = $imported > 0
            ? "{$imported} jutsu(s) importado(s)."
            : 'Nenhum jutsu novo para importar (já estavam na sua biblioteca).';
    }

    /** @return array<int,string> nomes (lowercase) dos jutsus do próprio usuário */
    protected function normalizedOwnNames(): array
    {
        return auth()->user()->jutsus()
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower($n))
            ->all();
    }

    public function render()
    {
        $character = $this->character();

        $assigned = $character->jutsus()->with('user')->orderBy('name')->get();

        $available = Jutsu::whereIn('user_id', $this->poolUserIds($character))
            ->with('user')
            ->orderBy('name')
            ->get();

        // Todas as tags existentes na biblioteca (para o filtro)
        $allTags = $available->flatMap(fn ($j) => $j->tags ?? [])->unique()->sort()->values();

        // Aplica filtro: jutsu precisa conter todas as tags selecionadas
        if ($this->activeFilters) {
            $available = $available->filter(
                fn ($j) => count(array_intersect($this->activeFilters, $j->tags ?? [])) === count($this->activeFilters)
            )->values();
        }

        return view('livewire.jutsu-panel', [
            'assigned'    => $assigned,
            'available'   => $available,
            'assignedIds' => $assigned->pluck('id')->all(),
            'allTags'     => $allTags,
            'authId'      => auth()->id(),
        ]);
    }
}
