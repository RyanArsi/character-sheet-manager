{{--
    Card de talento reutilizável.
    Tags e campos ficam sempre visíveis; o expandir (seta à esquerda da foto ou
    clique na foto) revela apenas a descrição e as infos.
    $talent : modelo Talent (com user carregado)
    $mode   : 'assigned' (na ficha) | 'available' (na biblioteca, fora da ficha) | 'in-sheet' (na biblioteca, já na ficha)
    $authId : id do usuário logado (para mostrar editar só ao criador)
--}}
@php
    $talentPayload = [
        'name'   => $talent->name,
        'type'   => 'talent',
        'test'   => $talent->test_dice,
        'damage' => $talent->damage_dice,
        'chakra' => $talent->chakra_cost,
        'media'  => $talent->media ? Storage::url($talent->media) : null,
        'volume' => $talent->volume ?? 100,
    ];
    $hasDetails = $talent->description || $talent->infos;
@endphp
<div x-data="{ expanded: false }" class="bg-gray-900 border border-gray-700 rounded-lg p-3 mb-2" dusk="talent-card-{{ $talent->id }}">
    <div class="flex items-start gap-3">
        {{-- Seta de expandir (à esquerda da foto) + imagem --}}
        <div class="flex items-center gap-1.5 flex-shrink-0">
            @if($hasDetails)
                <button type="button" @click="expanded = !expanded"
                    dusk="talent-details-{{ $talent->id }}"
                    title="Descrição / infos"
                    class="text-gray-500 hover:text-amber-300 text-xs w-3 text-center">
                    <span x-show="!expanded">▾</span>
                    <span x-show="expanded" x-cloak>▴</span>
                </button>
            @endif
            <div @if($hasDetails) @click="expanded = !expanded" title="Descrição / infos" @endif
                class="w-14 h-14 rounded-lg overflow-hidden bg-gray-800 ring-1 ring-gray-700 flex items-center justify-center {{ $hasDetails ? 'cursor-pointer' : '' }}">
                @if($talent->image)
                    <img src="{{ Storage::url($talent->image) }}" class="w-full h-full object-cover">
                @else
                    <span class="text-gray-700 text-xl">✦</span>
                @endif
            </div>
        </div>

        <div class="flex-1 min-w-0">
            {{-- Tags (em cima) --}}
            @if(!empty($talent->tags))
                <div class="flex flex-wrap gap-1 mb-1">
                    @foreach($talent->tags as $tag)
                        <button type="button" wire:click="openTag('{{ $tag }}')"
                            title="Ver significado"
                            class="px-1.5 py-0.5 text-[9px] rounded-full bg-gray-800 border border-gray-600 text-gray-300 hover:border-amber-400 hover:text-amber-300 transition-colors">{{ $tag }}</button>
                    @endforeach
                </div>
            @endif

            {{-- Nome + ações --}}
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-1.5 min-w-0">
                    @if($mode === 'assigned')
                        <button type="button" @click="$dispatch('use-jutsu', @js($talentPayload))"
                            dusk="talent-use-{{ $talent->id }}"
                            title="Usar talento (rola dados, desconta chakra)"
                            class="text-sm font-semibold text-white leading-tight text-left hover:text-amber-300 transition-colors truncate">
                            {{ $talent->name }}
                        </button>
                    @else
                        <h3 class="text-sm font-semibold text-white leading-tight truncate">{{ $talent->name }}</h3>
                    @endif
                    @if($talent->media)
                        @include('livewire.partials.media-button', ['url' => $talentPayload['media'], 'volume' => $talentPayload['volume']])
                    @endif
                    @if($talent->hidden)
                        <span title="Oculto (só você e o mestre veem)" class="text-gray-500 text-[11px] flex-shrink-0">🚫</span>
                    @endif
                </div>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    @if($talent->user_id === $authId)
                        <button type="button" wire:click="startEdit({{ $talent->id }})"
                            title="Editar" class="text-gray-500 hover:text-amber-400 text-xs">✎</button>
                    @endif

                    @if($mode === 'assigned' || $mode === 'in-sheet')
                        <button type="button" wire:click="unassign({{ $talent->id }})"
                            title="Remover da ficha"
                            class="text-gray-500 hover:text-red-400 text-xs">✕</button>
                    @endif

                    @if($mode === 'available')
                        <button type="button" wire:click="assign({{ $talent->id }})"
                            dusk="talent-assign-{{ $talent->id }}"
                            class="px-2 py-0.5 text-[10px] font-medium rounded bg-amber-600 hover:bg-amber-500 text-white">+ Ficha</button>
                    @endif

                    @if($mode === 'in-sheet')
                        <span class="text-[9px] text-green-400 font-medium">✓ na ficha</span>
                    @endif
                </div>
            </div>

            {{-- Atributos curtos --}}
            <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 mt-1.5 text-[11px] text-gray-400">
                @if($talent->chakra_cost)<div><span class="text-gray-600">Chakra:</span> {{ $talent->chakra_cost }}</div>@endif
                @if($talent->test_dice)<div><span class="text-gray-600">Teste:</span> <span class="font-mono text-gray-300">{{ $talent->test_dice }}</span></div>@endif
                @if($talent->damage_dice)<div><span class="text-gray-600">Dano:</span> <span class="font-mono text-gray-300">{{ $talent->damage_dice }}</span></div>@endif
                @if($talent->actions)<div><span class="text-gray-600">Ações:</span> {{ $talent->actions }}</div>@endif
                @if($talent->area_range)<div><span class="text-gray-600">Área/alc.:</span> {{ $talent->area_range }}</div>@endif
                @if($talent->target)<div><span class="text-gray-600">Alvo:</span> {{ $talent->target }}</div>@endif
            </div>
        </div>
    </div>

    {{-- Descrição e infos: reveladas ao expandir --}}
    @if($hasDetails)
        <div x-show="expanded" x-cloak class="mt-2">
            @if($talent->description)
                <p class="text-[11px] text-gray-300 whitespace-pre-line">{{ $talent->description }}</p>
            @endif
            @if($talent->infos)
                <p class="text-[10px] text-gray-500 mt-1 whitespace-pre-line"><span class="text-gray-600">Infos:</span> {{ $talent->infos }}</p>
            @endif
        </div>
    @endif

    {{-- Autor --}}
    <p class="text-[9px] text-gray-600 mt-2 text-right">por {{ $talent->user->name ?? '—' }}</p>
</div>
