{{--
    Card de ação (de perícia) reutilizável.
    $action : modelo Action (com user carregado)
    $mode   : 'assigned' | 'available' | 'in-sheet'
    $authId : id do usuário logado (editar só para o criador)
--}}
@php
    $actionPayload = [
        'name'   => $action->name,
        'type'   => 'action',
        'test'   => $action->test_dice,
        'damage' => $action->damage_dice,
    ];
    $hasDetails = (bool) $action->description;
@endphp
<div x-data="{ expanded: false }" class="bg-gray-900 border border-gray-700 rounded-lg p-3 mb-2" dusk="action-card-{{ $action->id }}">
    <div class="flex items-start justify-between gap-2">
        <div class="flex items-center gap-1.5 min-w-0">
            @if($hasDetails)
                <button type="button" @click="expanded = !expanded"
                    title="Descrição" class="text-gray-500 hover:text-amber-300 text-xs w-3 text-center">
                    <span x-show="!expanded">▾</span>
                    <span x-show="expanded" x-cloak>▴</span>
                </button>
            @endif
            @if($mode === 'assigned')
                <button type="button" @click="$dispatch('use-jutsu', @js($actionPayload))"
                    dusk="action-use-{{ $action->id }}"
                    title="Usar ação (rola os dados)"
                    class="text-sm font-semibold text-white leading-tight text-left hover:text-amber-300 transition-colors truncate">
                    {{ $action->name }}
                </button>
            @else
                <h3 class="text-sm font-semibold text-white leading-tight truncate">{{ $action->name }}</h3>
            @endif
        </div>
        <div class="flex items-center gap-1.5 flex-shrink-0">
            @if($action->user_id === $authId)
                <button type="button" wire:click="startEdit({{ $action->id }})"
                    title="Editar" class="text-gray-500 hover:text-amber-400 text-xs">✎</button>
            @endif

            @if($mode === 'assigned' || $mode === 'in-sheet')
                <button type="button" wire:click="unassign({{ $action->id }})"
                    title="Remover da ficha" class="text-gray-500 hover:text-red-400 text-xs">✕</button>
            @endif

            @if($mode === 'available')
                <button type="button" wire:click="assign({{ $action->id }})"
                    dusk="action-assign-{{ $action->id }}"
                    class="px-2 py-0.5 text-[10px] font-medium rounded bg-amber-600 hover:bg-amber-500 text-white">+ Ficha</button>
            @endif

            @if($mode === 'in-sheet')
                <span class="text-[9px] text-green-400 font-medium">✓ na ficha</span>
            @endif
        </div>
    </div>

    {{-- Tags --}}
    @if(!empty($action->tags))
        <div class="flex flex-wrap gap-1 mt-1.5">
            @foreach($action->tags as $tag)
                <button type="button" wire:click="openTag('{{ $tag }}')"
                    title="Ver significado"
                    class="px-1.5 py-0.5 text-[9px] rounded-full bg-gray-800 border border-gray-600 text-gray-300 hover:border-amber-400 hover:text-amber-300 transition-colors">{{ $tag }}</button>
            @endforeach
        </div>
    @endif

    {{-- Dados --}}
    <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 mt-1.5 text-[11px] text-gray-400">
        @if($action->test_dice)<div><span class="text-gray-600">Teste:</span> <span class="font-mono text-gray-300">{{ $action->test_dice }}</span></div>@endif
        @if($action->damage_dice)<div><span class="text-gray-600">Dano:</span> <span class="font-mono text-gray-300">{{ $action->damage_dice }}</span></div>@endif
    </div>

    {{-- Descrição --}}
    @if($hasDetails)
        <div x-show="expanded" x-cloak class="mt-2">
            <p class="text-[11px] text-gray-300 whitespace-pre-line">{{ $action->description }}</p>
        </div>
    @endif

    <p class="text-[9px] text-gray-600 mt-2 text-right">por {{ $action->user->name ?? '—' }}</p>
</div>
