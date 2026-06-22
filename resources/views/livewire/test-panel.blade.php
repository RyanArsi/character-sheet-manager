<div>
    {{-- ============ LISTA (testes da ficha) ============ --}}
    @if($view === 'list')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest">Testes</h2>
            <button type="button" wire:click="startCreate"
                dusk="test-create"
                class="px-2.5 py-1 text-[11px] font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                + Adicionar teste
            </button>
        </div>

        <div class="space-y-2">
            @forelse($tests as $test)
                @php
                    $testPayload = [
                        'name'   => $test->name,
                        'type'   => 'test',
                        'test'   => $test->test_dice,
                        'damage' => $test->damage_dice,
                    ];
                @endphp
                <div class="bg-gray-900 border border-gray-700 rounded-lg p-3" dusk="test-card-{{ $test->id }}">
                    <div class="flex items-start justify-between gap-2">
                        {{-- Nome: clicar rola o teste --}}
                        <button type="button" @click="$dispatch('use-jutsu', @js($testPayload))"
                            dusk="test-roll-{{ $test->id }}"
                            title="Rolar teste"
                            class="flex-1 min-w-0 text-left text-sm font-semibold text-white leading-tight hover:text-amber-300 transition-colors truncate">
                            🎲 {{ $test->name }}
                        </button>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <button type="button" wire:click="startEdit({{ $test->id }})"
                                dusk="test-edit-{{ $test->id }}"
                                title="Editar" class="text-gray-500 hover:text-amber-400 text-xs">✎</button>
                            <button type="button" wire:click="deleteTest({{ $test->id }})"
                                wire:confirm="Excluir este teste?"
                                title="Excluir" class="text-gray-500 hover:text-red-400 text-xs">✕</button>
                        </div>
                    </div>

                    {{-- Dados (teste / dano) --}}
                    <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 mt-1.5 text-[11px] text-gray-400">
                        @if($test->test_dice)<div><span class="text-gray-600">Teste:</span> <span class="font-mono text-gray-300">{{ $test->test_dice }}</span></div>@endif
                        @if($test->damage_dice)<div><span class="text-gray-600">Dano:</span> <span class="font-mono text-gray-300">{{ $test->damage_dice }}</span></div>@endif
                    </div>
                </div>
            @empty
                <p class="text-gray-600 text-sm">Nenhum teste ainda. Clique em <span class="text-gray-400">+ Adicionar teste</span> para criar o primeiro.</p>
            @endforelse
        </div>
    @endif

    {{-- ============ FORMULÁRIO (criar / editar) ============ --}}
    @if($view === 'form')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest">
                {{ $editingId ? 'Editar teste' : 'Novo teste' }}
            </h2>
            <button type="button" wire:click="cancelForm"
                dusk="test-cancel"
                class="px-2.5 py-1 text-[11px] font-medium rounded bg-gray-700 hover:bg-gray-600 text-gray-200 transition-colors">
                ← Voltar
            </button>
        </div>

        <form wire:submit="save" class="space-y-3">
            {{-- Nome --}}
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Nome</label>
                <input type="text" wire:model="name" dusk="test-name" autofocus
                    class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
                @error('name') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Rolagens: teste e dano --}}
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Teste</label>
                    <input type="text" wire:model="test_dice" dusk="test-test-dice" placeholder="ex.: d20+taijutsu"
                        class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm font-mono focus:border-amber-500 focus:ring-0 focus:outline-none">
                    @error('test_dice') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Dano</label>
                    <input type="text" wire:model="damage_dice" dusk="test-damage-dice" placeholder="ex.: 4d6+forca"
                        class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm font-mono focus:border-amber-500 focus:ring-0 focus:outline-none">
                    @error('damage_dice') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-[10px] text-gray-600 -mt-1">Mesma notação da aba Dados — referencie a ficha pelo <span class="font-mono text-gray-500">nome</span> (ex.: <span class="font-mono text-gray-500">d20+taijutsu</span>).</p>

            <div class="flex items-center justify-between pt-1">
                @if($editingId)
                    <button type="button" wire:click="deleteTest({{ $editingId }})"
                        wire:confirm="Excluir este teste?"
                        dusk="test-delete"
                        class="text-[11px] text-red-500 hover:text-red-400">Excluir</button>
                @else
                    <span></span>
                @endif
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    dusk="test-save"
                    class="px-4 py-1.5 text-xs font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors disabled:opacity-50">
                    Salvar
                </button>
            </div>
        </form>
    @endif
</div>
