<?php

namespace App\Livewire;

use App\Models\Campaign;
use App\Models\CampaignSheetGroup;
use App\Models\Character;
use App\Support\SkillDefinitions;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CampaignSheets extends Component
{
    #[Locked]
    public int $campaignId;

    public string $newGroupName = '';

    public function mount(int $campaignId): void
    {
        $this->authorizeOwner($this->campaign($campaignId));
        $this->campaignId = $campaignId;
    }

    protected function campaign(?int $id = null): Campaign
    {
        return Campaign::findOrFail($id ?? $this->campaignId);
    }

    /** Só o mestre (dono) gerencia as fichas da campanha. */
    protected function authorizeOwner(Campaign $campaign): void
    {
        abort_unless($campaign->owner_id === auth()->id(), 403);
    }

    // ---------- Grupos ----------
    public function createGroup(): void
    {
        $this->authorizeOwner($this->campaign());
        $this->validate(['newGroupName' => 'required|string|max:60']);

        $this->campaign()->sheetGroups()->create(['name' => trim($this->newGroupName)]);
        $this->newGroupName = '';
    }

    public function deleteGroup(int $groupId): void
    {
        $this->authorizeOwner($this->campaign());

        $group = $this->campaign()->sheetGroups()->findOrFail($groupId);
        // As fichas do grupo ficam sem grupo (não são apagadas).
        $group->characters()->update(['sheet_group_id' => null]);
        $group->delete();
    }

    public function moveSheet(int $characterId, ?int $groupId = null): void
    {
        $this->authorizeOwner($this->campaign());

        $character = $this->campaign()->npcSheets()->findOrFail($characterId);

        if ($groupId) {
            // grupo precisa ser desta campanha
            $this->campaign()->sheetGroups()->findOrFail($groupId);
        }

        $character->update(['sheet_group_id' => $groupId ?: null]);
    }

    // ---------- Fichas ----------
    public function createSheet(?int $groupId = null)
    {
        $this->authorizeOwner($this->campaign());

        if ($groupId) {
            $this->campaign()->sheetGroups()->findOrFail($groupId);
        }

        $character = DB::transaction(function () use ($groupId) {
            $character = Character::create([
                'user_id'        => auth()->id(),
                'campaign_id'    => $this->campaignId,
                'sheet_group_id' => $groupId ?: null,
            ]);

            foreach (SkillDefinitions::ALL as $def) {
                $character->skills()->create([
                    'name'      => $def['name'],
                    'attribute' => $def['attribute'],
                ]);
            }

            return $character;
        });

        return redirect()->route('fichas.editar', $character);
    }

    public function deleteSheet(int $characterId): void
    {
        $this->authorizeOwner($this->campaign());

        $this->campaign()->npcSheets()->findOrFail($characterId)->delete();
    }

    public function render()
    {
        $campaign = $this->campaign();

        $groups = $campaign->sheetGroups()->orderBy('name')->get();

        $sheets = $campaign->npcSheets()
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'village', 'avatar', 'sheet_group_id']);

        return view('livewire.campaign-sheets', [
            'groups'   => $groups,
            'sheets'   => $sheets,
        ]);
    }
}
