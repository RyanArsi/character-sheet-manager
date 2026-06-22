<div x-data="combatPanel(@js($combatData))" wire:key="combat-root-{{ $combatKey }}">

    {{-- ===== Ordem de iniciativa ===== --}}
    <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-bold text-amber-400 uppercase tracking-wider">Ordem de Iniciativa</h3>
            <span class="text-xs text-gray-400">Rodada {{ $initiative['round'] ?? 1 }}</span>
        </div>
        @if(empty($initiative['entries']))
            <p class="text-gray-500 text-xs">Sem iniciativa ainda. Role a iniciativa nas fichas para montar a ordem.</p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach($initiative['entries'] as $e)
                    <div @class([
                        'flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs',
                        'bg-amber-500/15 border-amber-500 text-amber-200' => ($initiative['current_id'] ?? null) === $e['id'],
                        'bg-gray-900 border-gray-700 text-gray-300' => ($initiative['current_id'] ?? null) !== $e['id'],
                    ])>
                        @if(($initiative['current_id'] ?? null) === $e['id'])
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                        @endif
                        <span class="font-semibold">{{ $e['name'] }}</span>
                        <span class="text-gray-500">{{ $e['roll'] }}</span>
                        @if(!empty($e['is_npc']))<span class="text-[9px] text-rose-300">NPC</span>@endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ===== Adicionar combatente ===== --}}
    <div class="mb-5">
        @if($available->isEmpty())
            <p class="text-gray-500 text-xs">Sem fichas para adicionar. Crie fichas de NPC na aba <span class="text-gray-300">Fichas</span>.</p>
        @else
            <div class="flex items-center gap-2" x-data="{ sel: '' }">
                <select x-model="sel"
                    class="bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-sm text-gray-200 focus:border-amber-500 focus:ring-0 focus:outline-none">
                    <option value="">Adicionar ficha ao combate…</option>
                    @foreach($available as $opt)
                        <option value="{{ $opt->id }}">{{ $opt->name ?: 'Sem nome' }}</option>
                    @endforeach
                </select>
                <button type="button" x-show="sel" dusk="combat-add"
                    @click="$wire.addCombatant(parseInt(sel)); sel = ''"
                    class="px-3 py-1.5 text-xs font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                    + Adicionar
                </button>
            </div>
        @endif
    </div>

    {{-- ===== Combatentes ===== --}}
    @forelse($combatants as $c)
        @php
            $hpPct = $c->hp_max > 0 ? min(100, round(($c->hp_current / $c->hp_max) * 100)) : 0;
            $ckPct = $c->chakra_max > 0 ? min(100, round(($c->chakra_current / $c->chakra_max) * 100)) : 0;
        @endphp
        <div wire:key="combatant-{{ $c->id }}" dusk="combat-card-{{ $c->id }}"
            class="bg-gray-800 border border-gray-700 rounded-lg p-3 mb-3">

            {{-- Cabeçalho: foto + nome (clique amplia) --}}
            <div class="flex items-center gap-3">
                <button type="button" @click="toggle({{ $c->id }})" class="flex items-center gap-3 min-w-0 flex-1 text-left">
                    <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-900 ring-1 ring-gray-700 flex items-center justify-center flex-shrink-0">
                        @if($c->avatar)
                            <img src="{{ Storage::url($c->avatar) }}" alt="" class="w-full h-full object-cover">
                        @else
                            <span class="text-gray-700 text-xl">🥷</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-white truncate">{{ $c->name ?: 'Sem nome' }}</p>
                        <p class="text-[10px] text-gray-500">Nível {{ $c->level }}@if($c->village) · {{ $c->village }}@endif</p>
                    </div>
                </button>
                <span class="text-gray-500 text-xs" x-text="expandedId === {{ $c->id }} ? '▴' : '▾'"></span>
            </div>

            {{-- Barras de vida e chakra (editáveis) --}}
            <div class="mt-3 space-y-1.5">
                <div class="flex items-center gap-1.5">
                    <button type="button" wire:click="adjustHp({{ $c->id }}, -5)" class="text-xs font-bold text-gray-400 hover:text-red-400 px-0.5">«</button>
                    <button type="button" wire:click="adjustHp({{ $c->id }}, -1)" class="text-xs font-bold text-gray-400 hover:text-red-400 px-0.5">‹</button>
                    <div class="relative flex-1 h-6 bg-gray-700 rounded overflow-hidden">
                        <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-red-800 to-red-500 transition-all duration-200" style="width: {{ $hpPct }}%"></div>
                        <div class="absolute inset-0 flex items-center justify-center text-[11px] font-bold text-white">
                            ❤ {{ $c->hp_current }} / {{ $c->hp_max }}
                        </div>
                    </div>
                    <button type="button" wire:click="adjustHp({{ $c->id }}, 1)" class="text-xs font-bold text-gray-400 hover:text-red-400 px-0.5">›</button>
                    <button type="button" wire:click="adjustHp({{ $c->id }}, 5)" class="text-xs font-bold text-gray-400 hover:text-red-400 px-0.5">»</button>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" wire:click="adjustChakra({{ $c->id }}, -5)" class="text-xs font-bold text-gray-400 hover:text-blue-400 px-0.5">«</button>
                    <button type="button" wire:click="adjustChakra({{ $c->id }}, -1)" class="text-xs font-bold text-gray-400 hover:text-blue-400 px-0.5">‹</button>
                    <div class="relative flex-1 h-6 bg-gray-700 rounded overflow-hidden">
                        <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-blue-800 to-cyan-500 transition-all duration-200" style="width: {{ $ckPct }}%"></div>
                        <div class="absolute inset-0 flex items-center justify-center text-[11px] font-bold text-white">
                            ✦ {{ $c->chakra_current }} / {{ $c->chakra_max }}
                        </div>
                    </div>
                    <button type="button" wire:click="adjustChakra({{ $c->id }}, 1)" class="text-xs font-bold text-gray-400 hover:text-blue-400 px-0.5">›</button>
                    <button type="button" wire:click="adjustChakra({{ $c->id }}, 5)" class="text-xs font-bold text-gray-400 hover:text-blue-400 px-0.5">»</button>
                </div>
            </div>

            {{-- ===== Ampliado ===== --}}
            <div x-show="expandedId === {{ $c->id }}" x-cloak class="mt-3 pt-3 border-t border-gray-700">

                {{-- Sub-abas --}}
                <div class="flex items-center gap-1 mb-3">
                    @foreach(['habilidades' => 'Habilidades', 'status' => 'Status'] as $sk => $sl)
                        <button type="button" @click="setSub({{ $c->id }}, '{{ $sk }}')"
                            :class="sub({{ $c->id }}) === '{{ $sk }}' ? 'bg-gray-700 text-amber-300' : 'bg-gray-900 text-gray-400 hover:text-gray-200'"
                            class="px-3 py-1 text-xs font-medium rounded transition-colors">{{ $sl }}</button>
                    @endforeach
                </div>

                {{-- Resultado da última rolagem --}}
                <p x-show="results[{{ $c->id }}]" x-cloak x-text="results[{{ $c->id }}]"
                    class="text-xs text-amber-300 bg-gray-900 border border-gray-700 rounded px-2 py-1.5 mb-3"></p>

                {{-- Habilidades --}}
                <div x-show="sub({{ $c->id }}) === 'habilidades'">
                    @php
                        $sections = [
                            ['Jutsus', $c->jutsus], ['Talentos', $c->talents],
                            ['Ações', $c->actions], ['Equipamentos', $c->equipments], ['Testes', $c->tests],
                        ];
                        $anyAbility = collect($sections)->contains(fn ($s) => $s[1]->isNotEmpty());
                    @endphp
                    @forelse($sections as [$label, $items])
                        @if($items->isNotEmpty())
                            <div class="mb-2">
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">{{ $label }}</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($items as $i)
                                        <button type="button"
                                            @click="roll({{ $c->id }}, @js($i->name), @js($i->test_dice), @js($i->damage_dice))"
                                            class="px-2 py-1 text-[11px] rounded bg-gray-900 border border-gray-700 text-gray-200 hover:border-amber-400 hover:text-amber-300 transition-colors">
                                            🎲 {{ $i->name }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @empty
                    @endforelse
                    @unless($anyAbility)
                        <p class="text-gray-600 text-xs">Sem habilidades atribuídas. Adicione na ficha (botão abaixo).</p>
                    @endunless
                </div>

                {{-- Status --}}
                <div x-show="sub({{ $c->id }}) === 'status'" x-cloak>
                    @php
                        $attrs = [
                            'Força' => $c->forca, 'Agilidade' => $c->agilidade, 'Constituição' => $c->constituicao,
                            'Inteligência' => $c->inteligencia, 'Sabedoria' => $c->sabedoria, 'Carisma' => $c->carisma,
                        ];
                        $specs = ['Ninjutsu' => $c->ninjutsu, 'Genjutsu' => $c->genjutsu, 'Taijutsu' => $c->taijutsu];
                    @endphp
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Atributos</p>
                    <div class="grid grid-cols-3 gap-1.5 mb-3">
                        @foreach($attrs as $label => $val)
                            <button type="button" @click="rollAttr({{ $c->id }}, @js($label), {{ (int) $val }})"
                                class="flex items-center justify-between px-2 py-1 text-[11px] rounded bg-gray-900 border border-gray-700 text-gray-200 hover:border-amber-400">
                                <span>{{ $label }}</span><span class="font-mono text-amber-300">{{ $val }}</span>
                            </button>
                        @endforeach
                    </div>
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Especializações</p>
                    <div class="grid grid-cols-3 gap-1.5 mb-3">
                        @foreach($specs as $label => $val)
                            <button type="button" @click="rollAttr({{ $c->id }}, @js($label), {{ (int) $val }})"
                                class="flex items-center justify-between px-2 py-1 text-[11px] rounded bg-gray-900 border border-gray-700 text-gray-200 hover:border-amber-400">
                                <span>{{ $label }}</span><span class="font-mono text-amber-300">{{ $val }}</span>
                            </button>
                        @endforeach
                    </div>
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Perícias</p>
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach($c->skills as $skill)
                            @php $sv = (int) $skill->value + (int) $skill->training_level * 2; @endphp
                            <button type="button" @click="rollAttr({{ $c->id }}, @js($skill->name), {{ $sv }})"
                                class="flex items-center justify-between px-2 py-1 text-[11px] rounded bg-gray-900 border border-gray-700 text-gray-300 hover:border-amber-400">
                                <span class="truncate">{{ $skill->name }}</span><span class="font-mono text-amber-300 ml-1">{{ $sv }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Rodapé --}}
                <div class="flex items-center justify-between mt-3 pt-2 border-t border-gray-700">
                    <a href="{{ route('fichas.editar', $c->id) }}"
                        class="text-[11px] text-amber-400 hover:underline">Editar ficha completa</a>
                    <button type="button" wire:click="removeCombatant({{ $c->id }})"
                        class="text-[11px] text-gray-500 hover:text-red-400">Remover do combate</button>
                </div>
            </div>
        </div>
    @empty
        <p class="text-gray-500 text-sm py-6 text-center">Nenhum combatente. Adicione fichas de NPC acima para começar o combate.</p>
    @endforelse
</div>
