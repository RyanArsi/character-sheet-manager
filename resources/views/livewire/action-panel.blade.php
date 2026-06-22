<div>
    {{-- ============ LISTA (ações da ficha) ============ --}}
    @if($view === 'list')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest">Ações</h2>
            <div class="flex items-center gap-2">
                {{-- Engrenagem: compartilha a config de uso com jutsus/talentos --}}
                <div class="relative" x-data="{ cfgOpen: false }">
                    <button type="button" @click="cfgOpen = !cfgOpen" dusk="action-config"
                        title="Configurações de uso"
                        class="w-7 h-7 flex items-center justify-center rounded bg-gray-700 hover:bg-gray-600 text-gray-300 transition-colors">⚙</button>
                    <div x-show="cfgOpen" x-cloak @click.outside="cfgOpen = false"
                        x-transition.opacity
                        class="absolute right-0 mt-1 w-52 bg-gray-800 border border-gray-600 rounded-lg shadow-2xl p-3 z-50 space-y-2">
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Ao usar a ação</p>
                        <label class="flex items-center gap-2 text-xs text-gray-300 cursor-pointer">
                            <input type="checkbox" x-model="jutsuCfg.test" dusk="acfg-test"
                                class="rounded border-gray-600 bg-gray-900 text-amber-500 focus:ring-0 focus:ring-offset-0">
                            Rolar teste
                        </label>
                        <label class="flex items-center gap-2 text-xs text-gray-300 cursor-pointer">
                            <input type="checkbox" x-model="jutsuCfg.damage" dusk="acfg-damage"
                                class="rounded border-gray-600 bg-gray-900 text-amber-500 focus:ring-0 focus:ring-offset-0">
                            Rolar dano
                        </label>
                    </div>
                </div>
                <button type="button" wire:click="$set('view', 'browse')"
                    dusk="action-browse"
                    class="px-2.5 py-1 text-[11px] font-medium rounded bg-gray-700 hover:bg-gray-600 text-gray-200 transition-colors">
                    Biblioteca
                </button>
                <button type="button" wire:click="startCreate"
                    dusk="action-create"
                    class="px-2.5 py-1 text-[11px] font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                    + Criar
                </button>
            </div>
        </div>

        @forelse($assigned as $action)
            @include('livewire.partials.action-card', ['action' => $action, 'mode' => 'assigned', 'authId' => $authId])
        @empty
            <p class="text-gray-600 text-sm">Nenhuma ação nesta ficha ainda. Use <span class="text-gray-400">Biblioteca</span> para atribuir ou <span class="text-gray-400">Criar</span> uma nova.</p>
        @endforelse
    @endif

    {{-- ============ BIBLIOTECA (atribuir / filtrar) ============ --}}
    @if($view === 'browse')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest">Biblioteca de ações</h2>
            <button type="button" wire:click="$set('view', 'list')"
                class="px-2.5 py-1 text-[11px] font-medium rounded bg-gray-700 hover:bg-gray-600 text-gray-200 transition-colors">
                ← Voltar
            </button>
        </div>

        {{-- Filtro por tags --}}
        @if($allTags->isNotEmpty())
            <div class="flex flex-wrap gap-1.5 mb-4">
                @foreach($allTags as $tag)
                    <button type="button" wire:click="toggleFilter('{{ $tag }}')"
                        @class([
                            'px-2 py-0.5 text-[10px] rounded-full border transition-colors',
                            'bg-amber-600 border-amber-500 text-white' => in_array($tag, $activeFilters, true),
                            'bg-gray-800 border-gray-600 text-gray-300 hover:border-gray-400' => ! in_array($tag, $activeFilters, true),
                        ])>
                        {{ $tag }}
                    </button>
                @endforeach
            </div>
        @endif

        @forelse($available as $action)
            @include('livewire.partials.action-card', [
                'action' => $action,
                'mode' => in_array($action->id, $assignedIds, true) ? 'in-sheet' : 'available',
                'authId' => $authId,
            ])
        @empty
            <p class="text-gray-600 text-sm">Nenhuma ação encontrada{{ $activeFilters ? ' para essas tags' : '' }}.</p>
        @endforelse
    @endif

    {{-- ============ FORMULÁRIO (criar / editar) ============ --}}
    @if($view === 'form')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest">
                {{ $editingId ? 'Editar ação' : 'Nova ação' }}
            </h2>
            <button type="button" wire:click="cancelForm"
                class="px-2.5 py-1 text-[11px] font-medium rounded bg-gray-700 hover:bg-gray-600 text-gray-200 transition-colors">
                Cancelar
            </button>
        </div>

        <form wire:submit="save" class="space-y-3">
            {{-- Nome --}}
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Nome</label>
                <input type="text" wire:model="name" dusk="action-name"
                    class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
                @error('name') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Tags --}}
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Tags <span class="text-gray-600 normal-case">(separadas por vírgula)</span></label>
                <input type="text" wire:model="tagsInput" placeholder="ex.: percepção, social, atletismo"
                    class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
            </div>

            {{-- Rolagens: teste e dano --}}
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Dados (teste)</label>
                    <input type="text" wire:model="test_dice" dusk="action-test-dice" placeholder="ex.: d20+inteligencia"
                        class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm font-mono focus:border-amber-500 focus:ring-0 focus:outline-none">
                    @error('test_dice') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Dano</label>
                    <input type="text" wire:model="damage_dice" dusk="action-damage-dice" placeholder="ex.: 2d6"
                        class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm font-mono focus:border-amber-500 focus:ring-0 focus:outline-none">
                    @error('damage_dice') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-[10px] text-gray-600 -mt-1">Mesma notação da aba Dados — referencie a ficha pelo <span class="font-mono text-gray-500">nome</span> (ex.: <span class="font-mono text-gray-500">d20+inteligencia</span>).</p>

            {{-- Descrição --}}
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Descrição</label>
                <textarea wire:model="description" rows="3"
                    class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none resize-y"></textarea>
            </div>

            <div class="flex items-center justify-between pt-1">
                @if($editingId)
                    <button type="button" wire:click="deleteAction({{ $editingId }})"
                        wire:confirm="Excluir esta ação? Ela será removida de todas as fichas."
                        class="text-[11px] text-red-500 hover:text-red-400">Excluir</button>
                @else
                    <span></span>
                @endif
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    dusk="action-save"
                    class="px-4 py-1.5 text-xs font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors disabled:opacity-50">
                    Salvar
                </button>
            </div>
        </form>
    @endif

    {{-- ============ CAIXINHA: significado da tag ============ --}}
    @if($tagName !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:key="tag-popover">
            <div class="absolute inset-0 bg-black/50" wire:click="closeTag"></div>

            <div class="relative w-72 bg-gray-800 border border-amber-500/40 rounded-xl shadow-2xl p-4">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="px-2 py-0.5 text-[11px] rounded-full bg-gray-900 border border-gray-600 text-amber-300">{{ $tagName }}</span>
                    <div class="flex items-center gap-2 text-gray-400">
                        @unless($editingTag)
                            <button type="button" wire:click="editTag"
                                title="Editar significado" class="hover:text-amber-400 text-sm">✎</button>
                        @endunless
                        <button type="button" wire:click="closeTag" title="Fechar" class="hover:text-white text-sm">✕</button>
                    </div>
                </div>

                @if($editingTag)
                    <textarea wire:model="tagDescription" rows="4"
                        placeholder="O que essa tag significa?"
                        class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none resize-y"></textarea>
                    @error('tagDescription') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
                    <div class="flex justify-end mt-2">
                        <button type="button" wire:click="saveTag"
                            class="px-3 py-1.5 text-xs font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                            Salvar
                        </button>
                    </div>
                @else
                    <p class="text-sm text-gray-300 whitespace-pre-line">
                        {{ $tagDescription !== '' ? $tagDescription : 'Sem descrição ainda. Clique no lápis para adicionar.' }}
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
