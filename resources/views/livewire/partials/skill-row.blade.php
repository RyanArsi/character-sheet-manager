{{--
    Linha de perícia/resistência/combate (mesmo comportamento: treinamento ciclável + valor + rolagem).
    $i     : índice no array $skills (para wire:model / cycleTraining)
    $skill : item do array $skills
    $lock  : nome da variável Alpine que trava a edição (ex.: 'lockPericias', 'lockResist', 'lockCombate')
--}}
@php
    $lvl   = $skill['training_level'] ?? 0;
    $bonus = $lvl * 2;
    $lock  = $lock ?? 'lockPericias';
@endphp
<div class="flex items-center gap-1.5"
    @contextmenu.prevent="openSkillMenu($event, {{ $i }})"
    title="Botão direito: trocar o atributo de rolagem">
    {{-- Botão de treinamento ciclável --}}
    <button type="button"
        wire:click="cycleTraining({{ $i }})"
        :disabled="{{ $lock }}"
        title="{{ $lvl === 0 ? 'Sem treinamento' : '+'.($lvl * 2) }}"
        @class([
            'w-5 h-5 rounded border flex items-center justify-center text-[10px] font-black flex-shrink-0 transition-all duration-200 select-none disabled:cursor-not-allowed',
            'border-gray-600 bg-gray-800 text-transparent'        => $lvl === 0,
            'border-green-500 bg-green-900/40 text-green-400'     => $lvl === 1,
            'border-blue-500 bg-blue-900/40 text-blue-400'        => $lvl === 2,
            'border-yellow-400 bg-yellow-900/40 text-yellow-300'  => $lvl === 3,
            'border-orange-400 bg-orange-900/40 text-orange-300 training-glow-orange' => $lvl === 4,
            'border-red-500 bg-red-900/40 text-red-400 training-glow-red'             => $lvl === 5,
        ])
    >{{ $lvl > 0 ? '+'.$bonus : '' }}</button>

    {{-- Nome clicável (rola d20 + atributo padrão + valor + bônus de treinamento) --}}
    <button type="button"
        @click="rollSkill({{ $i }})"
        class="flex-1 text-xs leading-tight text-left hover:text-white transition-colors cursor-pointer {{ $lvl > 0 ? 'font-semibold text-white' : 'text-gray-300' }}">
        {{ $skill['name'] }}
        @if(!empty($skill['attribute']))
            <span class="text-gray-600 text-[10px]">({{ $skill['attribute'] }})</span>
        @endif
    </button>

    {{-- Valor --}}
    <input type="number"
        wire:model.live="skills.{{ $i }}.value"
        :disabled="{{ $lock }}"
        class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-white text-xs font-bold focus:border-amber-500 focus:ring-0 focus:outline-none disabled:opacity-40 disabled:cursor-not-allowed">
</div>
