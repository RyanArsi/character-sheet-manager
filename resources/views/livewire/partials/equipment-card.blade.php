{{--
    Card de equipamento reutilizável (compacto: recolhido mostra só nome + ações;
    expande para tags, atributos, local de carga, descrição/infos e autor).
    $equipment : modelo Equipment (com user carregado; pivot.location quando atribuído)
    $mode      : 'assigned' (na ficha) | 'available' (na biblioteca, fora da ficha) | 'in-sheet' (na biblioteca, já na ficha)
    $authId    : id do usuário logado (para mostrar editar só ao criador)
    $locations : locais de carga possíveis (para o seletor)
--}}
@php
    $equipmentPayload = [
        'name'   => $equipment->name,
        'type'   => 'equipment',
        'test'   => $equipment->test_dice,
        'damage' => $equipment->damage_dice,
        'chakra' => null,
        'media'  => $equipment->media ? Storage::url($equipment->media) : null,
        'volume' => $equipment->volume ?? 100,
    ];
    $locationLabels = ['mochila' => 'Mochila', 'carregando' => 'Carregando', 'pergaminhos' => 'Pergaminhos'];
@endphp
<div x-data="{ expanded: false }" class="bg-gray-900 border border-gray-700 rounded-lg px-2.5 py-2 mb-2" dusk="equipment-card-{{ $equipment->id }}">
    {{-- Cabeçalho (sempre visível) — linha enxuta --}}
    <div class="flex items-center gap-2">
        {{-- Expandir/recolher (à esquerda da foto) --}}
        <button type="button" @click="expanded = !expanded"
            dusk="equipment-details-{{ $equipment->id }}"
            title="Detalhes"
            class="text-gray-500 hover:text-amber-300 text-xs w-4 text-center flex-shrink-0">
            <span x-show="!expanded">▾</span>
            <span x-show="expanded" x-cloak>▴</span>
        </button>

        {{-- Imagem (tamanho limitado, sempre cover; clique também expande) --}}
        <div @click="expanded = !expanded"
            title="Detalhes"
            class="w-9 h-9 rounded-lg overflow-hidden bg-gray-800 ring-1 ring-gray-700 flex items-center justify-center flex-shrink-0 cursor-pointer">
            @if($equipment->image)
                <img src="{{ Storage::url($equipment->image) }}" class="w-full h-full object-cover">
            @else
                <span class="text-gray-700 text-base">🎒</span>
            @endif
        </div>

        {{-- Nome (com mais espaço em relação à foto) --}}
        <div class="flex-1 min-w-0 ml-2 flex items-center gap-1.5">
            @if($mode === 'assigned')
                <button type="button" @click="$dispatch('use-jutsu', @js($equipmentPayload))"
                    dusk="equipment-use-{{ $equipment->id }}"
                    title="Usar equipamento (rola dados)"
                    class="min-w-0 text-sm font-semibold text-white leading-tight text-left hover:text-amber-300 transition-colors truncate">
                    {{ $equipment->name }}
                </button>
            @else
                <h3 class="min-w-0 text-sm font-semibold text-white leading-tight truncate">{{ $equipment->name }}</h3>
            @endif
            @if($equipment->media)
                @include('livewire.partials.media-button', ['url' => $equipmentPayload['media'], 'volume' => $equipmentPayload['volume']])
            @endif
            @if($equipment->hidden)
                <span title="Oculto (só você e o mestre veem)" class="text-gray-500 text-[11px] flex-shrink-0">🚫</span>
            @endif
        </div>

        {{-- Ações --}}
        <div class="flex items-center gap-1.5 flex-shrink-0">
            @if($equipment->user_id === $authId)
                <button type="button" wire:click="startEdit({{ $equipment->id }})"
                    title="Editar" class="text-gray-500 hover:text-amber-400 text-xs">✎</button>
            @endif

            @if($mode === 'assigned' || $mode === 'in-sheet')
                <button type="button" wire:click="unassign({{ $equipment->id }})"
                    title="Remover da ficha"
                    class="text-gray-500 hover:text-red-400 text-xs">✕</button>
            @endif

            @if($mode === 'available')
                <button type="button" wire:click="assign({{ $equipment->id }})"
                    dusk="equipment-assign-{{ $equipment->id }}"
                    class="px-2 py-0.5 text-[10px] font-medium rounded bg-amber-600 hover:bg-amber-500 text-white">+ Ficha</button>
            @endif

            @if($mode === 'in-sheet')
                <span class="text-[9px] text-green-400 font-medium">✓ na ficha</span>
            @endif
        </div>
    </div>

    {{-- Detalhes (expandido) --}}
    <div x-show="expanded" x-cloak class="mt-2">
        {{-- Tags --}}
        @if(!empty($equipment->tags))
            <div class="flex flex-wrap gap-1 mb-1.5">
                @foreach($equipment->tags as $tag)
                    <button type="button" wire:click="openTag('{{ $tag }}')"
                        title="Ver significado"
                        class="px-1.5 py-0.5 text-[9px] rounded-full bg-gray-800 border border-gray-600 text-gray-300 hover:border-amber-400 hover:text-amber-300 transition-colors">{{ $tag }}</button>
                @endforeach
            </div>
        @endif

        {{-- Atributos curtos --}}
        <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-[11px] text-gray-400">
            @if(!is_null($equipment->space))<div><span class="text-gray-600">Espaço:</span> {{ $equipment->space }}</div>@endif
            @if($equipment->test_dice)<div><span class="text-gray-600">Teste:</span> <span class="font-mono text-gray-300">{{ $equipment->test_dice }}</span></div>@endif
            @if($equipment->damage_dice)<div><span class="text-gray-600">Dano:</span> <span class="font-mono text-gray-300">{{ $equipment->damage_dice }}</span></div>@endif
        </div>

        {{-- Seletor de local de carga (somente na ficha) --}}
        @if($mode === 'assigned')
            <div class="flex items-center gap-1.5 mt-2">
                <span class="text-[10px] text-gray-600 uppercase tracking-widest">Local</span>
                <select wire:change="setLocation({{ $equipment->id }}, $event.target.value)"
                    dusk="equipment-location-{{ $equipment->id }}"
                    class="bg-gray-800 border border-gray-700 rounded px-1.5 py-0.5 text-[11px] text-gray-200 focus:border-amber-500 focus:ring-0 focus:outline-none">
                    @foreach($locations as $loc)
                        <option value="{{ $loc }}" @selected(($equipment->pivot->location ?? 'mochila') === $loc)>{{ $locationLabels[$loc] ?? $loc }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Descrição e infos --}}
        @if($equipment->description)
            <p class="text-[11px] text-gray-300 mt-2 whitespace-pre-line">{{ $equipment->description }}</p>
        @endif
        @if($equipment->infos)
            <p class="text-[10px] text-gray-500 mt-1 whitespace-pre-line"><span class="text-gray-600">Infos:</span> {{ $equipment->infos }}</p>
        @endif

        {{-- Autor --}}
        <p class="text-[9px] text-gray-600 mt-2 text-right">por {{ $equipment->user->name ?? '—' }}</p>
    </div>
</div>
