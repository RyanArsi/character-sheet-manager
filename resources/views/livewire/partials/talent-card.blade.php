{{--
    Card de talento reutilizável.
    $talent : modelo Talent (com user carregado)
    $mode   : 'assigned' (na ficha) | 'available' (na biblioteca, fora da ficha) | 'in-sheet' (na biblioteca, já na ficha)
    $authId : id do usuário logado (para mostrar editar só ao criador)
--}}
@php
    $talentPayload = [
        'name'   => $talent->name,
        'test'   => $talent->test_dice,
        'damage' => $talent->damage_dice,
        'chakra' => $talent->chakra_cost,
        'media'  => $talent->media ? Storage::url($talent->media) : null,
        'volume' => $talent->volume ?? 100,
    ];
@endphp
<div class="bg-gray-900 border border-gray-700 rounded-lg p-3 mb-2" dusk="talent-card-{{ $talent->id }}">
    <div class="flex gap-3">
        {{-- Imagem --}}
        <div class="w-14 h-14 rounded-lg overflow-hidden bg-gray-800 ring-1 ring-gray-700 flex items-center justify-center flex-shrink-0">
            @if($talent->image)
                <img src="{{ Storage::url($talent->image) }}" class="w-full h-full object-cover">
            @else
                <span class="text-gray-700 text-xl">✦</span>
            @endif
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
                @if($mode === 'assigned')
                    <button type="button" @click="$dispatch('use-jutsu', @js($talentPayload))"
                        dusk="talent-use-{{ $talent->id }}"
                        title="Usar talento (rola dados, toca som, desconta chakra)"
                        class="text-sm font-semibold text-white leading-tight text-left hover:text-amber-300 transition-colors flex items-center gap-1">
                        {{ $talent->name }}
                        @if($talent->media)<span class="text-[10px] text-gray-500">🔊</span>@endif
                    </button>
                @else
                    <h3 class="text-sm font-semibold text-white leading-tight flex items-center gap-1">
                        {{ $talent->name }}
                        @if($talent->media)<span class="text-[10px] text-gray-500">🔊</span>@endif
                    </h3>
                @endif
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

    {{-- Descrição e infos --}}
    @if($talent->description)
        <p class="text-[11px] text-gray-300 mt-2 whitespace-pre-line">{{ $talent->description }}</p>
    @endif
    @if($talent->infos)
        <p class="text-[10px] text-gray-500 mt-1 whitespace-pre-line"><span class="text-gray-600">Infos:</span> {{ $talent->infos }}</p>
    @endif

    {{-- Autor --}}
    <p class="text-[9px] text-gray-600 mt-2 text-right">por {{ $talent->user->name ?? '—' }}</p>
</div>
