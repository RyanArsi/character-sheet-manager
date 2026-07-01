<?php

namespace App\Livewire;

use App\Models\Action;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ActionPanel extends Component
{
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

    // ---- Formulário (criador/editor) ----
    public ?int $editingId = null;
    public string $name = '';
    public string $tagsInput = '';
    public string $test_dice = '';
    public string $damage_dice = '';
    public string $description = '';

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

    /** Dono da ficha + todos os membros das campanhas em que a ficha está. */
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

    public function startEdit(int $actionId): void
    {
        $action = Action::where('user_id', auth()->id())->findOrFail($actionId);

        $this->editingId   = $action->id;
        $this->name        = $action->name;
        $this->tagsInput   = implode(', ', $action->tags ?? []);
        $this->test_dice   = $action->test_dice ?? '';
        $this->damage_dice = $action->damage_dice ?? '';
        $this->description = $action->description ?? '';
        $this->view        = 'form';
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->view = 'list';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'tagsInput', 'test_dice', 'damage_dice', 'description']);
    }

    // ---------- Persistência ----------
    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:120',
            'tagsInput'   => 'nullable|string|max:255',
            'test_dice'   => 'nullable|string|max:120',
            'damage_dice' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:5000',
        ]);

        $tags = Tag::canonicalList(explode(',', $this->tagsInput));

        $data = [
            'name'        => $this->name,
            'tags'        => $tags,
            'test_dice'   => $this->test_dice ?: null,
            'damage_dice' => $this->damage_dice ?: null,
            'description' => $this->description ?: null,
        ];

        if ($this->editingId) {
            $action = Action::where('user_id', auth()->id())->findOrFail($this->editingId);
            $action->update($data);
        } else {
            $action = auth()->user()->actions()->create($data);
            $this->character()->actions()->syncWithoutDetaching([$action->id]);
        }

        $this->resetForm();
        $this->view = 'list';
    }

    public function deleteAction(int $actionId): void
    {
        Action::where('user_id', auth()->id())->findOrFail($actionId)->delete();
        $this->view = 'list';
    }

    // ---------- Atribuição à ficha ----------
    public function assign(int $actionId): void
    {
        $character = $this->character();
        $pool = $this->poolUserIds($character);

        $action = Action::whereIn('user_id', $pool)->findOrFail($actionId);
        $character->actions()->syncWithoutDetaching([$action->id]);
    }

    public function unassign(int $actionId): void
    {
        $this->character()->actions()->detach($actionId);
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

    public function render()
    {
        $character = $this->character();
        $visible = $this->visibleFilter($character);

        $assigned = $character->actions()->with('user')->orderBy('name')->get()
            ->filter($visible)->values();

        $available = Action::whereIn('user_id', $this->poolUserIds($character))
            ->with('user')
            ->orderBy('name')
            ->get()
            ->filter($visible)->values();

        $allTags = $available->flatMap(fn ($a) => $a->tags ?? [])->unique()->sort()->values();

        if ($this->activeFilters) {
            $available = $available->filter(
                fn ($a) => count(array_intersect($this->activeFilters, $a->tags ?? [])) === count($this->activeFilters)
            )->values();
        }

        return view('livewire.action-panel', [
            'assigned'    => $assigned,
            'available'   => $available,
            'assignedIds' => $assigned->pluck('id')->all(),
            'allTags'     => $allTags,
            'authId'      => auth()->id(),
        ]);
    }
}
