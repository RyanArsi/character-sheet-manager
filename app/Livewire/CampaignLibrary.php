<?php

namespace App\Livewire;

use App\Models\Action;
use App\Models\Campaign;
use App\Models\Equipment;
use App\Models\Jutsu;
use App\Models\Tag;
use App\Models\Talent;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CampaignLibrary extends Component
{
    #[Locked]
    public int $campaignId;

    /** Filtro por tipo: all | jutsus | talents | equipments | actions */
    public string $type = 'all';

    /** Tags ativas no filtro */
    public array $activeFilters = [];

    // ---- Formulário (criar/editar item) — compartilha os campos com a edição na ficha ----
    public ?string $formType = null;   // jutsus | talents | equipments | actions (null = fechado)
    public ?int $formEditingId = null;
    public string $fName = '';
    public string $fTags = '';
    public string $fChakra = '';
    public string $fTest = '';
    public string $fDamage = '';
    public string $fActions = '';
    public string $fArea = '';
    public string $fTarget = '';
    public string $fSpace = '';
    public string $fDescription = '';
    public string $fInfos = '';

    public function mount(int $campaignId): void
    {
        $this->authorizeMember($this->campaign($campaignId));
        $this->campaignId = $campaignId;
    }

    protected function campaign(?int $id = null): Campaign
    {
        return Campaign::findOrFail($id ?? $this->campaignId);
    }

    protected function authorizeMember(Campaign $campaign): void
    {
        abort_unless(
            $campaign->members()->where('users.id', auth()->id())->exists(),
            403
        );
    }

    protected function isMaster(): bool
    {
        return $this->campaign()->owner_id === auth()->id();
    }

    /** Ids dos membros da campanha (mestre + jogadores) — dono dos itens da biblioteca. */
    protected function memberIds(): Collection
    {
        return $this->campaign()->members()->pluck('users.id');
    }

    protected function modelFor(string $type): string
    {
        return match ($type) {
            'jutsus'     => Jutsu::class,
            'talents'    => Talent::class,
            'equipments' => Equipment::class,
            'actions'    => Action::class,
            default      => abort(404),
        };
    }

    // ---------- Filtros ----------
    public function setType(string $type): void
    {
        $this->type = in_array($type, ['all', 'jutsus', 'talents', 'equipments', 'actions'], true)
            ? $type : 'all';
        $this->activeFilters = [];
        $this->cancelForm();
    }

    public function toggleFilter(string $tag): void
    {
        if (in_array($tag, $this->activeFilters, true)) {
            $this->activeFilters = array_values(array_diff($this->activeFilters, [$tag]));
        } else {
            $this->activeFilters[] = $tag;
        }
    }

    // ---------- Ocultar/mostrar (criador ou mestre) ----------
    public function toggleHidden(string $type, int $id): void
    {
        $model = $this->modelFor($type);
        $item = $model::whereIn('user_id', $this->memberIds())->findOrFail($id);

        abort_unless($item->user_id === auth()->id() || $this->isMaster(), 403);

        $item->update(['hidden' => ! $item->hidden]);
    }

    // ---------- Criar / editar / excluir (mesmos campos da ficha) ----------
    public function startCreate(string $type): void
    {
        $this->cancelForm();
        $this->modelFor($type); // valida o tipo
        $this->type = $type;
        $this->formType = $type;
    }

    public function startEdit(string $type, int $id): void
    {
        $model = $this->modelFor($type);
        // Só o criador edita o próprio item.
        $item = $model::where('user_id', auth()->id())->findOrFail($id);

        $this->cancelForm();
        $this->formType      = $type;
        $this->formEditingId = $item->id;
        $this->fName         = $item->name;
        $this->fTags         = implode(', ', $item->tags ?? []);
        $this->fChakra       = $item->chakra_cost ?? '';
        $this->fTest         = $item->test_dice ?? '';
        $this->fDamage       = $item->damage_dice ?? '';
        $this->fActions      = $item->actions ?? '';
        $this->fArea         = $item->area_range ?? '';
        $this->fTarget       = $item->target ?? '';
        $this->fSpace        = $item->space !== null ? (string) $item->space : '';
        $this->fDescription  = $item->description ?? '';
        $this->fInfos        = $item->infos ?? '';
        $this->type          = $type;
    }

    public function cancelForm(): void
    {
        $this->reset([
            'formType', 'formEditingId', 'fName', 'fTags', 'fChakra', 'fTest', 'fDamage',
            'fActions', 'fArea', 'fTarget', 'fSpace', 'fDescription', 'fInfos',
        ]);
    }

    public function save(): void
    {
        // Qualquer membro cria; só o criador edita o próprio item.
        $this->authorizeMember($this->campaign());
        $type = $this->formType;
        abort_if($type === null, 404);
        $model = $this->modelFor($type);

        $this->validate([
            'fName'        => 'required|string|max:120',
            'fTags'        => 'nullable|string|max:255',
            'fChakra'      => 'nullable|string|max:60',
            'fTest'        => 'nullable|string|max:120',
            'fDamage'      => 'nullable|string|max:120',
            'fActions'     => 'nullable|string|max:60',
            'fArea'        => 'nullable|string|max:120',
            'fTarget'      => 'nullable|string|max:120',
            'fSpace'       => 'nullable|integer|min:0',
            'fDescription' => 'nullable|string|max:5000',
            'fInfos'       => 'nullable|string|max:5000',
        ]);

        $tags = collect(explode(',', $this->fTags))
            ->map(fn ($t) => trim(str_replace(['"', "'"], '', $t)))
            ->filter()->unique()->values()->all();

        foreach ($tags as $t) {
            Tag::firstOrCreate(['name' => $t]);
        }

        // Campos comuns a todos os tipos
        $data = [
            'name'        => $this->fName,
            'tags'        => $tags,
            'test_dice'   => $this->fTest ?: null,
            'damage_dice' => $this->fDamage ?: null,
            'description' => $this->fDescription ?: null,
        ];

        // Campos específicos por tipo (espelham os da ficha)
        if ($type === 'jutsus' || $type === 'talents') {
            $data += [
                'chakra_cost' => $this->fChakra ?: null,
                'actions'     => $this->fActions ?: null,
                'area_range'  => $this->fArea ?: null,
                'target'      => $this->fTarget ?: null,
                'infos'       => $this->fInfos ?: null,
            ];
        } elseif ($type === 'equipments') {
            $data += [
                'space' => $this->fSpace !== '' ? (int) $this->fSpace : null,
                'infos' => $this->fInfos ?: null,
            ];
        }

        if ($this->formEditingId) {
            $item = $model::where('user_id', auth()->id())->findOrFail($this->formEditingId);
            $item->update($data);
        } else {
            $data['user_id'] = auth()->id();
            $model::create($data);
        }

        $this->cancelForm();
    }

    public function deleteItem(string $type, int $id): void
    {
        $model = $this->modelFor($type);
        $model::where('user_id', auth()->id())->findOrFail($id)->delete();
        $this->cancelForm();
    }

    // ---------- Render ----------
    protected function normalize($item, string $type): array
    {
        $uid = auth()->id();

        $fields = [];
        $add = function (string $label, $value) use (&$fields) {
            if ($value !== null && $value !== '') {
                $fields[] = ['label' => $label, 'value' => $value];
            }
        };

        if ($type === 'jutsus' || $type === 'talents') {
            $add('Chakra', $item->chakra_cost);
            $add('Teste', $item->test_dice);
            $add('Dano', $item->damage_dice);
            $add('Ações', $item->actions);
            $add('Área/alc.', $item->area_range);
            $add('Alvo', $item->target);
        } elseif ($type === 'equipments') {
            $add('Teste', $item->test_dice);
            $add('Dano', $item->damage_dice);
            $add('Espaço', $item->space);
        } else { // actions
            $add('Teste', $item->test_dice);
            $add('Dano', $item->damage_dice);
        }

        return [
            'type'        => $type,
            'id'          => $item->id,
            'name'        => $item->name,
            'tags'        => $item->tags ?? [],
            'fields'      => $fields,
            'description' => $item->description ?? null,
            'hidden'      => (bool) $item->hidden,
            'user_id'     => $item->user_id,
            'creator'     => $item->user->name ?? '—',
            'canHide'     => $item->user_id === $uid || $this->isMaster(),
            'canEdit'     => $item->user_id === $uid,
        ];
    }

    public function render()
    {
        $uid = auth()->id();
        $isMaster = $this->isMaster();
        $members = $this->memberIds();

        $sources = [
            'jutsus'     => Jutsu::whereIn('user_id', $members)->with('user')->orderBy('name')->get(),
            'talents'    => Talent::whereIn('user_id', $members)->with('user')->orderBy('name')->get(),
            'equipments' => Equipment::whereIn('user_id', $members)->with('user')->orderBy('name')->get(),
            'actions'    => Action::whereIn('user_id', $members)->with('user')->orderBy('name')->get(),
        ];

        // Itens ocultos só aparecem para o criador ou o mestre.
        $counts = [];
        $normalized = collect();
        foreach ($sources as $type => $coll) {
            $visible = $coll->filter(fn ($i) => ! $i->hidden || $i->user_id === $uid || $isMaster);
            $counts[$type] = $visible->count();

            if ($this->type === 'all' || $this->type === $type) {
                foreach ($visible as $item) {
                    $normalized->push($this->normalize($item, $type));
                }
            }
        }
        $counts['all'] = array_sum($counts);

        // Tags disponíveis no conjunto atual (antes do filtro por tag)
        $allTags = $normalized->flatMap(fn ($x) => $x['tags'])->unique()->sort()->values();

        if ($this->activeFilters) {
            $normalized = $normalized->filter(
                fn ($x) => count(array_intersect($this->activeFilters, $x['tags'])) === count($this->activeFilters)
            );
        }

        $items = $normalized->sortBy('name')->values();

        return view('livewire.campaign-library', [
            'items'    => $items,
            'allTags'  => $allTags,
            'counts'   => $counts,
            'isMaster' => $isMaster,
        ]);
    }
}
