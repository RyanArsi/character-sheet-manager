<div>
    @php $grouped = $sheets->groupBy('sheet_group_id'); @endphp

    {{-- Criar grupo --}}
    <form wire:submit="createGroup" class="flex items-center gap-2 mb-5">
        <input type="text" wire:model="newGroupName" maxlength="60"
            dusk="sheets-group-name"
            placeholder="Novo grupo (ex.: Vilões, NPCs)"
            class="flex-1 bg-gray-900 border border-gray-700 rounded px-3 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
        <button type="submit" dusk="sheets-group-create"
            class="px-3 py-1.5 text-xs font-medium rounded bg-gray-700 hover:bg-gray-600 text-gray-200 transition-colors whitespace-nowrap">
            + Criar grupo
        </button>
    </form>
    @error('newGroupName') <p class="text-[11px] text-red-400 -mt-4 mb-4">{{ $message }}</p> @enderror

    {{-- Grupos --}}
    @foreach($groups as $group)
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2 pb-1 border-b border-gray-700">
                <h3 class="text-sm font-bold text-amber-400 uppercase tracking-wider">{{ $group->name }}
                    <span class="text-gray-600 text-xs">({{ ($grouped[$group->id] ?? collect())->count() }})</span>
                </h3>
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="createSheet({{ $group->id }})"
                        dusk="sheets-create-{{ $group->id }}"
                        class="text-[11px] text-amber-400 hover:text-amber-300">+ Nova ficha</button>
                    <button type="button" wire:click="deleteGroup({{ $group->id }})"
                        wire:confirm="Excluir o grupo '{{ $group->name }}'? As fichas continuam, só ficam sem grupo."
                        class="text-[11px] text-gray-500 hover:text-red-400">Excluir grupo</button>
                </div>
            </div>

            @include('livewire.partials.campaign-sheet-grid', ['list' => $grouped[$group->id] ?? collect(), 'groups' => $groups])
        </div>
    @endforeach

    {{-- Sem grupo --}}
    <div class="mb-2">
        <div class="flex items-center justify-between mb-2 pb-1 border-b border-gray-700">
            <h3 class="text-sm font-bold text-gray-300 uppercase tracking-wider">Sem grupo
                <span class="text-gray-600 text-xs">({{ ($grouped[null] ?? collect())->count() }})</span>
            </h3>
            <button type="button" wire:click="createSheet"
                dusk="sheets-create-none"
                class="text-[11px] text-amber-400 hover:text-amber-300">+ Nova ficha</button>
        </div>

        @include('livewire.partials.campaign-sheet-grid', ['list' => $grouped[null] ?? collect(), 'groups' => $groups])
    </div>

    @if($sheets->isEmpty() && $groups->isEmpty())
        <p class="text-gray-500 text-sm py-6 text-center">Nenhuma ficha ainda. Crie um grupo e adicione fichas de NPCs, vilões, etc. Só você (mestre) vê estas fichas.</p>
    @endif
</div>
