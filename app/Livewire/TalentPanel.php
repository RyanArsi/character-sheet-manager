<?php

namespace App\Livewire;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\Tag;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class TalentPanel extends Component
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
    public string $rank = '';
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

    /** Usuários cujos talentos formam a biblioteca disponível para esta ficha:
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

    /** O usuário logado é mestre (dono) de alguma campanha em que a ficha está? */
    protected function isMaster(Character $character): bool
    {
        return Campaign::whereIn('id', $character->relatedCampaignIds())
            ->where('owner_id', auth()->id())
            ->exists();
    }

    /** Itens ocultos só aparecem para o criador ou o mestre. */
    protected function visibleFilter(Character $character): \Closure
    {
        $uid = auth()->id();
        $isMaster = $this->isMaster($character);

        return fn ($item) => ! $item->hidden || $item->user_id === $uid || $isMaster;
    }

    // ---------- Navegação ----------
    public function startCreate(): void
    {
        $this->resetForm();
        $this->view = 'form';
    }

    public function startEdit(int $talentId): void
    {
        $talent = Talent::where('user_id', auth()->id())->findOrFail($talentId);

        $this->editingId   = $talent->id;
        $this->name        = $talent->name;
        $this->rank        = $talent->rank ?? '';
        $this->tagsInput   = implode(', ', $talent->tags ?? []);
        $this->chakra_cost = $talent->chakra_cost ?? '';
        $this->test_dice   = $talent->test_dice ?? '';
        $this->damage_dice = $talent->damage_dice ?? '';
        $this->actions     = $talent->actions ?? '';
        $this->area_range  = $talent->area_range ?? '';
        $this->target      = $talent->target ?? '';
        $this->description = $talent->description ?? '';
        $this->infos       = $talent->infos ?? '';
        $this->imagePath   = $talent->image;
        $this->image       = null;
        $this->mediaPath   = $talent->media;
        $this->media       = null;
        $this->volume      = $talent->volume ?? 100;
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
            'editingId', 'name', 'rank', 'tagsInput', 'chakra_cost', 'test_dice', 'damage_dice', 'actions',
            'area_range', 'target', 'description', 'infos', 'image', 'imagePath', 'media', 'mediaPath', 'volume',
        ]);
    }

    // ---------- Persistência ----------
    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:120',
            'rank'        => 'nullable|string|max:60',
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

        $tags = Tag::canonicalList(explode(',', $this->tagsInput));

        $data = [
            'name'        => $this->name,
            'rank'        => $this->rank ?: null,
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
            $data['image'] = $this->image->store('talents', 'public');
        }

        if ($this->media) {
            $data['media'] = $this->media->store('talents/media', 'public');
        }

        if ($this->editingId) {
            // Só o criador edita o próprio talento
            $talent = Talent::where('user_id', auth()->id())->findOrFail($this->editingId);
            $talent->update($data);
        } else {
            $talent = auth()->user()->talents()->create($data);
            // Ao criar pela ficha, já atribui o talento a ela
            $this->character()->talents()->syncWithoutDetaching([$talent->id]);
        }

        $this->resetForm();
        $this->view = 'list';
    }

    public function deleteTalent(int $talentId): void
    {
        Talent::where('user_id', auth()->id())->findOrFail($talentId)->delete();
        $this->view = 'list';
    }

    // ---------- Atribuição à ficha ----------
    public function assign(int $talentId): void
    {
        $character = $this->character();
        $pool = $this->poolUserIds($character);

        $talent = Talent::whereIn('user_id', $pool)->findOrFail($talentId);
        $character->talents()->syncWithoutDetaching([$talent->id]);
    }

    public function unassign(int $talentId): void
    {
        $this->character()->talents()->detach($talentId);
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
        $canonical = Tag::canonicalName($name);
        if ($canonical === null) {
            return;
        }
        $tag = Tag::firstOrCreate(['name' => $canonical]);

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

    /** Exporta para JSON os talentos visíveis na biblioteca (próprios + dos membros das campanhas). */
    public function exportTalents()
    {
        $character = $this->character();

        $talents = Talent::whereIn('user_id', $this->poolUserIds($character))
            ->with('user')
            ->orderBy('name')
            ->get();

        $tagNames = $talents->flatMap(fn ($t) => $t->tags ?? [])->unique()->values();
        $tags = Tag::whereIn('name', $tagNames)
            ->get(['name', 'description'])
            ->map(fn ($t) => ['name' => $t->name, 'description' => $t->description])
            ->all();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'talents' => $talents->map(fn ($t) => [
                'name'        => $t->name,
                'rank'        => $t->rank,
                'tags'        => $t->tags ?? [],
                'chakra_cost' => $t->chakra_cost,
                'test_dice'   => $t->test_dice,
                'damage_dice' => $t->damage_dice,
                'actions'     => $t->actions,
                'area_range'  => $t->area_range,
                'target'      => $t->target,
                'description' => $t->description,
                'infos'       => $t->infos,
                'image'       => $t->image,
                'media'       => $t->media,
                'volume'      => $t->volume,
                'created_by'  => $t->user->name ?? null,
            ])->all(),
            'tags' => $tags,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(
            fn () => print ($json),
            'talentos-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    /** Importa talentos de um arquivo JSON, registrando-os na biblioteca do usuário. */
    public function importTalents(): void
    {
        $this->validate(['importFile' => 'required|file|max:5120']);

        $data = json_decode(file_get_contents($this->importFile->getRealPath()), true);

        if (! is_array($data) || ! isset($data['talents']) || ! is_array($data['talents'])) {
            $this->addError('importFile', 'Arquivo inválido: não contém uma lista de talentos.');

            return;
        }

        // Traz o dicionário de tags (preenche descrição só se ainda estiver vazia)
        foreach (($data['tags'] ?? []) as $t) {
            $name = Tag::canonicalName((string) ($t['name'] ?? ''));
            if ($name === null) {
                continue;
            }
            $tag = Tag::where('name', $name)->first();
            if ($tag && empty($tag->description) && ! empty($t['description'])) {
                $tag->update(['description' => $t['description']]);
            }
        }

        // Nomes que o usuário já tem, para não duplicar
        $existing = $this->normalizedOwnNames();
        $imported = 0;

        foreach ($data['talents'] as $t) {
            if (empty($t['name']) || in_array(mb_strtolower($t['name']), $existing, true)) {
                continue;
            }

            $tags = Tag::canonicalList((array) ($t['tags'] ?? []));

            auth()->user()->talents()->create([
                'name'        => mb_substr((string) $t['name'], 0, 120),
                'rank'        => $t['rank'] ?? null,
                'tags'        => $tags,
                'chakra_cost' => $t['chakra_cost'] ?? null,
                'test_dice'   => $t['test_dice'] ?? null,
                'damage_dice' => $t['damage_dice'] ?? null,
                'actions'     => $t['actions'] ?? null,
                'area_range'  => $t['area_range'] ?? null,
                'target'      => $t['target'] ?? null,
                'description' => $t['description'] ?? null,
                'infos'       => $t['infos'] ?? null,
                'image'       => $t['image'] ?? null,
                'media'       => $t['media'] ?? null,
                'volume'      => $t['volume'] ?? 100,
            ]);

            $existing[] = mb_strtolower($t['name']);
            $imported++;
        }

        $this->importFile = null;
        $this->importMessage = $imported > 0
            ? "{$imported} talento(s) importado(s)."
            : 'Nenhum talento novo para importar (já estavam na sua biblioteca).';
    }

    /** @return array<int,string> nomes (lowercase) dos talentos do próprio usuário */
    protected function normalizedOwnNames(): array
    {
        return auth()->user()->talents()
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower($n))
            ->all();
    }

    public function render()
    {
        $character = $this->character();
        $visible = $this->visibleFilter($character);

        $assigned = $character->talents()->with('user')->orderBy('name')->get()
            ->filter($visible)->values();

        $available = Talent::whereIn('user_id', $this->poolUserIds($character))
            ->with('user')
            ->orderBy('name')
            ->get()
            ->filter($visible)->values();

        // Todas as tags existentes na biblioteca (para o filtro)
        $allTags = $available->flatMap(fn ($t) => $t->tags ?? [])->unique()->sort()->values();

        // Aplica filtro: talento precisa conter todas as tags selecionadas
        if ($this->activeFilters) {
            $available = $available->filter(
                fn ($t) => count(array_intersect($this->activeFilters, $t->tags ?? [])) === count($this->activeFilters)
            )->values();
        }

        return view('livewire.talent-panel', [
            'assigned'    => $assigned,
            'available'   => $available,
            'assignedIds' => $assigned->pluck('id')->all(),
            'allTags'     => $allTags,
            'authId'      => auth()->id(),
        ]);
    }
}
