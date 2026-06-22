<div>
    @php
        $typeLabels = ['all' => 'Todos', 'jutsus' => 'Jutsus', 'talents' => 'Talentos', 'equipments' => 'Equipamentos', 'actions' => 'Ações'];
        $typeBadge = [
            'jutsus'     => 'text-sky-300 bg-sky-500/15',
            'talents'    => 'text-orange-300 bg-orange-500/15',
            'equipments' => 'text-emerald-300 bg-emerald-500/15',
            'actions'    => 'text-amber-300 bg-amber-500/15',
        ];
    @endphp

    {{-- Filtro por tipo --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        @foreach($typeLabels as $key => $label)
            <button type="button" wire:click="setType('{{ $key }}')"
                dusk="lib-type-{{ $key }}"
                class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors
                    {{ $type === $key
                        ? 'bg-amber-600 border-amber-500 text-white'
                        : 'bg-gray-900 border-gray-700 text-gray-300 hover:border-amber-400' }}">
                {{ $label }}
                <span class="ml-1 text-[10px] opacity-70">{{ $counts[$key] ?? 0 }}</span>
            </button>
        @endforeach

        <div class="flex-1"></div>

        @php $singular = ['jutsus' => 'jutsu', 'talents' => 'talento', 'equipments' => 'equipamento', 'actions' => 'ação']; @endphp
        @if($type !== 'all')
            <button type="button" wire:click="startCreate('{{ $type }}')"
                dusk="lib-create-{{ $type }}"
                class="px-3 py-1.5 text-xs font-medium rounded-lg bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                + Criar {{ $singular[$type] }}
            </button>
        @endif
    </div>

    {{-- Formulário (criar/editar) — espelha os campos da ficha --}}
    @if($formType)
        @php $singularF = ['jutsus' => 'jutsu', 'talents' => 'talento', 'equipments' => 'equipamento', 'actions' => 'ação'][$formType]; @endphp
        <form wire:submit="save" class="bg-gray-900 border border-gray-700 rounded-lg p-4 mb-4 space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-bold text-amber-400">{{ $formEditingId ? 'Editar' : 'Novo(a)' }} {{ $singularF }}</h4>
                <button type="button" wire:click="cancelForm" class="text-xs text-gray-400 hover:text-gray-200">Cancelar</button>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Nome</label>
                <input type="text" wire:model="fName" dusk="lib-form-name"
                    class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
                @error('fName') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Tags <span class="text-gray-600 normal-case">(separadas por vírgula)</span></label>
                <input type="text" wire:model="fTags" placeholder="ex.: ofensivo, físico"
                    class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
            </div>

            {{-- Campos específicos de jutsu/talento --}}
            @if(in_array($formType, ['jutsus', 'talents']))
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Custo de chakra</label>
                        <input type="text" wire:model="fChakra" class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Ações</label>
                        <input type="text" wire:model="fActions" class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Área/alcance</label>
                        <input type="text" wire:model="fArea" class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Alvo</label>
                        <input type="text" wire:model="fTarget" class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
                    </div>
                </div>
            @endif

            {{-- Espaço (equipamento) --}}
            @if($formType === 'equipments')
                <div class="w-1/2">
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Espaço</label>
                    <input type="number" wire:model="fSpace" min="0" class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
                    @error('fSpace') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            {{-- Rolagens (todos os tipos) --}}
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Dados (teste)</label>
                    <input type="text" wire:model="fTest" dusk="lib-form-test" placeholder="ex.: d20+inteligencia"
                        class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm font-mono focus:border-amber-500 focus:ring-0 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Dano</label>
                    <input type="text" wire:model="fDamage" placeholder="ex.: 4d6+forca"
                        class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm font-mono focus:border-amber-500 focus:ring-0 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Descrição</label>
                <textarea wire:model="fDescription" rows="2"
                    class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none resize-y"></textarea>
            </div>

            @if(in_array($formType, ['jutsus', 'talents', 'equipments']))
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Infos</label>
                    <textarea wire:model="fInfos" rows="2"
                        class="w-full bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none resize-y"></textarea>
                </div>
            @endif

            <div class="flex items-center justify-between">
                @if($formEditingId)
                    <button type="button" wire:click="deleteItem('{{ $formType }}', {{ $formEditingId }})"
                        wire:confirm="Excluir este item? Ele será removido de todas as fichas."
                        class="text-[11px] text-red-500 hover:text-red-400">Excluir</button>
                @else
                    <span></span>
                @endif
                <button type="submit" dusk="lib-form-save"
                    class="px-4 py-1.5 text-xs font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                    Salvar
                </button>
            </div>
        </form>

        @if(in_array($formType, ['jutsus', 'talents', 'equipments']))
            <p class="text-[10px] text-gray-600 mb-4 -mt-2">Imagem e som ficam disponíveis ao editar na aba da ficha. Aqui você ajusta os campos de texto e rolagem.</p>
        @endif
    @endif

    {{-- Filtro por tags --}}
    @if($allTags->isNotEmpty())
        <div class="flex flex-wrap gap-1.5 mb-4">
            @foreach($allTags as $tag)
                <button type="button" wire:click="toggleFilter('{{ $tag }}')"
                    class="px-2 py-0.5 text-[11px] rounded-full border transition-colors
                        {{ in_array($tag, $activeFilters)
                            ? 'bg-amber-500/20 border-amber-400 text-amber-300'
                            : 'bg-gray-900 border-gray-600 text-gray-300 hover:border-amber-400' }}">
                    {{ $tag }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- Itens --}}
    @if($items->isEmpty())
        <p class="text-gray-500 text-sm py-8 text-center">Nenhum item na biblioteca para este filtro.</p>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
            @foreach($items as $item)
                <div x-data="{ open: false }"
                    class="bg-gray-900 border rounded-lg p-2.5 {{ $item['hidden'] ? 'border-gray-700/60 opacity-75' : 'border-gray-700' }}"
                    dusk="lib-card-{{ $item['type'] }}-{{ $item['id'] }}">
                    <div class="flex items-start justify-between gap-1.5">
                        <div class="min-w-0">
                            <span class="inline-block px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded {{ $typeBadge[$item['type']] }}">
                                {{ $typeLabels[$item['type']] }}
                            </span>
                            <h4 class="text-[13px] font-semibold text-white truncate mt-1 flex items-center gap-1">
                                {{ $item['name'] }}
                                @if($item['hidden'])
                                    <span title="Oculto" class="text-gray-500 text-[11px]">🚫</span>
                                @endif
                            </h4>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            @if($item['canEdit'])
                                <button type="button" wire:click="startEdit('{{ $item['type'] }}', {{ $item['id'] }})"
                                    dusk="lib-edit-{{ $item['type'] }}-{{ $item['id'] }}"
                                    title="Editar" class="text-gray-500 hover:text-amber-400 text-xs">✎</button>
                            @endif
                            @if($item['canHide'])
                                <button type="button" wire:click="toggleHidden('{{ $item['type'] }}', {{ $item['id'] }})"
                                    dusk="lib-hide-{{ $item['type'] }}-{{ $item['id'] }}"
                                    title="{{ $item['hidden'] ? 'Mostrar para todos' : 'Ocultar (só você e o mestre veem)' }}"
                                    class="text-xs {{ $item['hidden'] ? 'text-amber-400 hover:text-amber-300' : 'text-gray-500 hover:text-gray-300' }}">
                                    {{ $item['hidden'] ? '🙈' : '👁' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Tags --}}
                    @if(!empty($item['tags']))
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            @foreach($item['tags'] as $tag)
                                <button type="button" wire:click="toggleFilter('{{ $tag }}')"
                                    class="px-1.5 py-0.5 text-[9px] rounded-full bg-gray-800 border border-gray-600 text-gray-300 hover:border-amber-400 hover:text-amber-300 transition-colors">{{ $tag }}</button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Campos --}}
                    @if(!empty($item['fields']))
                        <div class="mt-1.5 text-[11px] text-gray-400 space-y-0.5">
                            @foreach($item['fields'] as $field)
                                <div class="truncate"><span class="text-gray-600">{{ $field['label'] }}:</span> <span class="font-mono text-gray-300">{{ $field['value'] }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Descrição --}}
                    @if($item['description'])
                        <button type="button" @click="open = !open" class="text-[10px] text-gray-500 hover:text-amber-300 mt-2">
                            <span x-show="!open">▾ descrição</span>
                            <span x-show="open" x-cloak>▴ ocultar</span>
                        </button>
                        <p x-show="open" x-cloak class="text-[11px] text-gray-300 whitespace-pre-line mt-1">{{ $item['description'] }}</p>
                    @endif

                    <p class="text-[9px] text-gray-600 mt-2 text-right">por {{ $item['creator'] }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
