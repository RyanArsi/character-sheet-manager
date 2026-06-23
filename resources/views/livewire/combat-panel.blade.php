<div x-data="combatPanel(@js($combatData), @js($events))" x-init="initEcho({{ $campaignId }})"
    wire:key="combat-root-{{ $combatKey }}"
    class="flex flex-col lg:flex-row gap-4">

    {{-- ===== Coluna 1: Eventos da campanha (feed ao vivo) ===== --}}
    <div class="lg:w-56 flex-shrink-0">
        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider mb-2">Eventos</h3>
        <div class="space-y-1.5 lg:max-h-[75vh] overflow-y-auto sidebar-scroll pr-1">
            <template x-if="!feed.length">
                <p class="text-gray-600 text-xs">Sem eventos ainda.</p>
            </template>
            <template x-for="(ev, i) in feed" :key="ev.id ?? i">
                <div class="bg-gray-900 border border-gray-700 rounded-lg px-2.5 py-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] font-semibold text-amber-400 truncate" x-text="ev.actor"></span>
                        <span class="text-[9px] text-gray-500 flex-shrink-0" x-text="ev.time"></span>
                    </div>
                    <div class="text-[11px] text-gray-200 break-words" x-html="fmtCampaignEvent(ev.message, ev.detail, true)"></div>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== Coluna 2: Ordem de iniciativa (com gestão) ===== --}}
    @php
        $entries = $initiative['entries'] ?? [];
        $conditions = $initiative['conditions'] ?? [];
        $condByTarget = collect($conditions)->groupBy('target_id');
    @endphp
    <div class="flex-1 min-w-0 space-y-3" dusk="combat-initiative">
        {{-- Cabeçalho: rodada + controles --}}
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <h3 class="text-xs font-bold text-amber-500 uppercase tracking-widest">Iniciativa</h3>
                <span class="text-[11px] text-gray-300 bg-gray-800 rounded-full px-2 py-0.5">Rodada <span class="font-bold">{{ $initiative['round'] ?? 1 }}</span></span>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" wire:click="passTurn" dusk="combat-pass-turn"
                    class="px-2.5 h-7 rounded-md bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold">Passar turno ▸</button>
                @if(!empty($entries))
                    <button type="button" wire:click="clearInitiative" wire:confirm="Limpar iniciativa e zerar rodadas?"
                        class="px-2 h-7 rounded-md bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs">Limpar</button>
                @endif
            </div>
        </div>

        {{-- Ordem da iniciativa --}}
        <div class="space-y-1.5">
            @forelse($entries as $idx => $e)
                @php $isCurrent = ($initiative['current_id'] ?? null) === $e['id']; @endphp
                <div @class([
                    'flex items-center gap-2 rounded-lg px-2.5 py-1.5 border transition-colors',
                    'border-red-500/60 bg-red-500/10' => $isCurrent,
                    'border-gray-700 bg-gray-800/50' => ! $isCurrent,
                ])>
                    <span @class([
                        'w-2.5 h-2.5 rounded-full flex-shrink-0',
                        'bg-red-500 animate-pulse' => $isCurrent,
                        'bg-gray-700' => ! $isCurrent,
                    ])></span>
                    <span class="text-[10px] text-gray-500 w-4 text-right flex-shrink-0">{{ $idx + 1 }}</span>
                    <span class="text-sm text-gray-100 truncate">{{ $e['name'] }}</span>
                    @if(!empty($e['is_npc']))<span class="text-[9px] uppercase font-bold text-rose-300 bg-rose-900/40 rounded px-1 py-0.5 flex-shrink-0">NPC</span>@endif
                    @if(isset($condByTarget[$e['id']]))
                        @foreach($condByTarget[$e['id']] as $cond)
                            <span class="inline-flex items-center gap-1 text-[9px] text-purple-200 bg-purple-900/50 rounded px-1 py-0.5 flex-shrink-0"
                                title="{{ $cond['name'] }} — {{ $cond['turns_left'] }} turno(s)">
                                {{ $cond['name'] }}<span class="opacity-60">{{ $cond['turns_left'] }}</span>
                                <button type="button" wire:click="removeCondition('{{ $cond['id'] }}')" class="text-purple-400 hover:text-red-400">×</button>
                            </span>
                        @endforeach
                    @endif
                    <span class="flex-1"></span>
                    <span class="text-sm font-bold text-amber-400 w-7 text-right flex-shrink-0">{{ $e['roll'] }}</span>
                    <button type="button" wire:click="removeEntry('{{ $e['id'] }}')" title="Remover da iniciativa"
                        class="text-gray-600 hover:text-red-400 text-xs flex-shrink-0">✕</button>
                </div>
            @empty
                <p class="text-xs text-gray-600">Ninguém na iniciativa ainda. Use o <span class="text-amber-400">⚔</span> nos combatentes ou adicione um NPC abaixo.</p>
            @endforelse
        </div>

        {{-- Mestre: adicionar NPC --}}
        <div x-data="{ name: '', roll: 10 }" class="flex items-center gap-1.5">
            <input type="text" x-model="name" placeholder="Nome do NPC" maxlength="60"
                @keydown.enter="if(name.trim()){ $wire.addNpcEntry(name, parseInt(roll)||0); name=''; roll=10; }"
                class="flex-1 min-w-0 bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 focus:border-amber-500 focus:ring-0 focus:outline-none">
            <input type="number" x-model.number="roll" title="Valor de iniciativa"
                class="w-14 bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 text-center [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
            <button type="button" @click="if(name.trim()){ $wire.addNpcEntry(name, parseInt(roll)||0); name=''; roll=10; }"
                class="px-2.5 h-7 rounded-md bg-rose-700 hover:bg-rose-600 text-white text-xs font-semibold whitespace-nowrap">+ NPC</button>
        </div>

        {{-- Condições & eventos --}}
        @if(!empty($entries))
            <div class="pt-3 border-t border-gray-700">
                <h3 class="text-xs font-bold text-amber-500 uppercase tracking-widest mb-2">Condições &amp; Eventos</h3>
                <div x-data="{ cname: '', target: '', turns: 1 }" class="flex items-center gap-1.5">
                    <input type="text" x-model="cname" placeholder="Nome da condição" dusk="combat-cond-name"
                        @keydown.enter="if(cname.trim()&&target){ $wire.addCondition(cname, target, parseInt(turns)||1); cname=''; target=''; turns=1; }"
                        class="flex-1 min-w-0 bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 focus:border-amber-500 focus:ring-0 focus:outline-none">
                    <select x-model="target" dusk="combat-cond-target"
                        class="bg-gray-800 border border-gray-700 rounded px-1 py-1 text-xs text-gray-200 max-w-[10rem] focus:border-amber-500 focus:ring-0 focus:outline-none">
                        <option value="">está em…</option>
                        @foreach($entries as $e)
                            <option value="{{ $e['id'] }}">{{ $e['name'] }}</option>
                        @endforeach
                    </select>
                    <input type="number" min="1" x-model.number="turns" title="Turnos para acabar"
                        class="w-12 bg-gray-800 border border-gray-700 rounded px-1 py-1 text-xs text-gray-200 text-center [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                    <button type="button" dusk="combat-add-condition"
                        @click="if(cname.trim()&&target){ $wire.addCondition(cname, target, parseInt(turns)||1); cname=''; target=''; turns=1; }"
                        class="px-2.5 h-7 rounded-md bg-purple-700 hover:bg-purple-600 text-white text-xs font-semibold whitespace-nowrap">+ Cond.</button>
                </div>
            </div>
        @endif
    </div>

    {{-- ===== Coluna 3: Combatentes (resumo das fichas) ===== --}}
    <div class="lg:w-80 flex-shrink-0">
        <div class="mb-3">
            @if($available->isEmpty())
                <p class="text-gray-600 text-xs">Sem fichas para adicionar. Crie NPCs na aba <span class="text-gray-300">Fichas</span>.</p>
            @else
                <div class="flex items-center gap-2" x-data="{ sel: '' }">
                    <select x-model="sel" dusk="combat-add-select"
                        class="bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 focus:border-amber-500 focus:ring-0 focus:outline-none">
                        <option value="">Adicionar ao combate…</option>
                        @foreach($available as $opt)
                            <option value="{{ $opt->id }}">{{ $opt->name ?: 'Sem nome' }}</option>
                        @endforeach
                    </select>
                    <button type="button" x-show="sel" dusk="combat-add"
                        @click="$wire.addCombatant(parseInt(sel)); sel = ''"
                        class="px-2.5 py-1 text-xs font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors">+ Adicionar</button>
                </div>
            @endif
        </div>

        <div class="space-y-2">
            @forelse($combatants as $c)
                @php
                    $hpPct = $c->hp_max > 0 ? min(100, round(($c->hp_current / $c->hp_max) * 100)) : 0;
                    $ckPct = $c->chakra_max > 0 ? min(100, round(($c->chakra_current / $c->chakra_max) * 100)) : 0;
                @endphp
                <div wire:key="combatant-{{ $c->id }}" dusk="combat-card-{{ $c->id }}"
                    class="bg-gray-800 border border-gray-700 rounded-lg p-2">

                    {{-- Cabeçalho --}}
                    <div class="flex items-center gap-2">
                        <button type="button" @click="toggle({{ $c->id }})" dusk="combat-expand-{{ $c->id }}" class="flex items-center gap-2 min-w-0 flex-1 text-left">
                            <div class="w-9 h-9 rounded-md overflow-hidden bg-gray-900 ring-1 ring-gray-700 flex items-center justify-center flex-shrink-0">
                                @if($c->avatar)
                                    <img src="{{ Storage::url($c->avatar) }}" alt="" class="w-full h-full object-cover">
                                @else
                                    <span class="text-gray-700 text-base">🥷</span>
                                @endif
                            </div>
                            <span class="font-semibold text-white text-xs truncate">{{ $c->name ?: 'Sem nome' }}</span>
                        </button>
                        <button type="button" wire:click="rollInitiative({{ $c->id }})"
                            dusk="combat-init-{{ $c->id }}"
                            title="Rolar iniciativa e entrar na ordem"
                            class="text-amber-400 hover:text-amber-300 text-xs flex-shrink-0">⚔</button>
                        <span class="text-gray-500 text-[10px]" x-text="expandedId === {{ $c->id }} ? '▴' : '▾'"></span>
                    </div>

                    {{-- Barras --}}
                    <div class="mt-1.5 space-y-1">
                        <div class="flex items-center gap-1">
                            <button type="button" wire:click="adjustHp({{ $c->id }}, -5)" dusk="combat-hp-down-{{ $c->id }}" class="text-[10px] font-bold text-gray-500 hover:text-red-400">«</button>
                            <button type="button" wire:click="adjustHp({{ $c->id }}, -1)" class="text-[10px] font-bold text-gray-500 hover:text-red-400">‹</button>
                            <div class="relative flex-1 h-3 bg-gray-700 rounded overflow-hidden">
                                <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-red-800 to-red-500" style="width: {{ $hpPct }}%"></div>
                                <div class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-white leading-none">{{ $c->hp_current }}/{{ $c->hp_max }}</div>
                            </div>
                            <button type="button" wire:click="adjustHp({{ $c->id }}, 1)" class="text-[10px] font-bold text-gray-500 hover:text-red-400">›</button>
                            <button type="button" wire:click="adjustHp({{ $c->id }}, 5)" class="text-[10px] font-bold text-gray-500 hover:text-red-400">»</button>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" wire:click="adjustChakra({{ $c->id }}, -5)" class="text-[10px] font-bold text-gray-500 hover:text-blue-400">«</button>
                            <button type="button" wire:click="adjustChakra({{ $c->id }}, -1)" class="text-[10px] font-bold text-gray-500 hover:text-blue-400">‹</button>
                            <div class="relative flex-1 h-3 bg-gray-700 rounded overflow-hidden">
                                <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-blue-800 to-cyan-500" style="width: {{ $ckPct }}%"></div>
                                <div class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-white leading-none">{{ $c->chakra_current }}/{{ $c->chakra_max }}</div>
                            </div>
                            <button type="button" wire:click="adjustChakra({{ $c->id }}, 1)" class="text-[10px] font-bold text-gray-500 hover:text-blue-400">›</button>
                            <button type="button" wire:click="adjustChakra({{ $c->id }}, 5)" class="text-[10px] font-bold text-gray-500 hover:text-blue-400">»</button>
                        </div>
                    </div>

                    {{-- Ampliado --}}
                    <div x-show="expandedId === {{ $c->id }}" x-cloak class="mt-2 pt-2 border-t border-gray-700">
                        <div class="flex items-center gap-1 mb-2">
                            @foreach(['habilidades' => 'Habilidades', 'status' => 'Status'] as $sk => $sl)
                                <button type="button" @click="setSub({{ $c->id }}, '{{ $sk }}')" dusk="combat-sub-{{ $sk }}-{{ $c->id }}"
                                    :class="sub({{ $c->id }}) === '{{ $sk }}' ? 'bg-gray-700 text-amber-300' : 'bg-gray-900 text-gray-400 hover:text-gray-200'"
                                    class="px-2 py-0.5 text-[11px] font-medium rounded transition-colors">{{ $sl }}</button>
                            @endforeach
                        </div>

                        {{-- Habilidades --}}
                        <div x-show="sub({{ $c->id }}) === 'habilidades'">
                            @php
                                $sections = [
                                    ['Jutsus', $c->jutsus, 'jutsu'], ['Talentos', $c->talents, 'talent'],
                                    ['Ações', $c->actions, 'action'], ['Equipamentos', $c->equipments, 'equipment'], ['Testes', $c->tests, 'test'],
                                ];
                                $anyAbility = collect($sections)->contains(fn ($s) => $s[1]->isNotEmpty());
                            @endphp
                            @foreach($sections as [$label, $items, $atype])
                                @if($items->isNotEmpty())
                                    <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-1 mt-1.5">{{ $label }}</p>
                                    <div class="space-y-1">
                                        @foreach($items as $i)
                                            @php
                                                $media = $i->media ?? null;
                                                $hasInfo = ($i->description ?? null) || ($i->infos ?? null)
                                                    || ($i->chakra_cost ?? null) || ($i->area_range ?? null)
                                                    || ($i->target ?? null) || ($i->space ?? null) || ($i->actions ?? null);
                                            @endphp
                                            <div x-data="{ info: false }" class="bg-gray-900 border border-gray-700 rounded px-1.5 py-1">
                                                <div class="flex items-center gap-1.5">
                                                    <button type="button"
                                                        @click="roll_({{ $c->id }}, @js($atype), @js($i->name), @js($i->test_dice), @js($i->damage_dice))"
                                                        class="min-w-0 text-left text-[11px] text-gray-200 hover:text-amber-300 truncate">🎲 {{ $i->name }}</button>
                                                    @if($media)
                                                        <button type="button" @click="playMedia(@js(Storage::url($media)), {{ (int) ($i->volume ?? 100) }})"
                                                            title="Tocar som (toda a campanha ouve)" class="text-gray-500 hover:text-amber-300 text-[11px] flex-shrink-0">🔊</button>
                                                    @endif
                                                    @if($hasInfo)
                                                        <button type="button" @click="info = !info" title="Detalhes" class="text-gray-500 hover:text-amber-300 text-[11px] flex-shrink-0">ⓘ</button>
                                                    @endif
                                                </div>
                                                @if($hasInfo)
                                                    <div x-show="info" x-cloak class="mt-1 text-[10px] text-gray-400 space-y-0.5">
                                                        <div class="flex flex-wrap gap-x-2 gap-y-0.5 font-mono">
                                                            @if($i->chakra_cost ?? null)<span><span class="text-gray-600">Chakra:</span> {{ $i->chakra_cost }}</span>@endif
                                                            @if($i->actions ?? null)<span><span class="text-gray-600">Ações:</span> {{ $i->actions }}</span>@endif
                                                            @if($i->area_range ?? null)<span><span class="text-gray-600">Área:</span> {{ $i->area_range }}</span>@endif
                                                            @if($i->target ?? null)<span><span class="text-gray-600">Alvo:</span> {{ $i->target }}</span>@endif
                                                            @if($i->space ?? null)<span><span class="text-gray-600">Espaço:</span> {{ $i->space }}</span>@endif
                                                        </div>
                                                        @if($i->description ?? null)<p class="text-gray-300 whitespace-pre-line">{{ $i->description }}</p>@endif
                                                        @if($i->infos ?? null)<p class="text-gray-500 whitespace-pre-line">{{ $i->infos }}</p>@endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                            @unless($anyAbility)
                                <p class="text-gray-600 text-[11px]">Sem habilidades. Adicione na ficha.</p>
                            @endunless
                        </div>

                        {{-- Status --}}
                        <div x-show="sub({{ $c->id }}) === 'status'" x-cloak>
                            @php
                                $attrs = [
                                    'Força' => $c->forca, 'Agilidade' => $c->agilidade, 'Constituição' => $c->constituicao,
                                    'Inteligência' => $c->inteligencia, 'Sabedoria' => $c->sabedoria, 'Carisma' => $c->carisma,
                                    'Ninjutsu' => $c->ninjutsu, 'Genjutsu' => $c->genjutsu, 'Taijutsu' => $c->taijutsu,
                                ];
                            @endphp
                            <div class="flex flex-wrap gap-1 mb-2">
                                @foreach($attrs as $label => $val)
                                    <button type="button" @click="rollAttr({{ $c->id }}, @js($label), {{ (int) $val }})"
                                        dusk="combat-stat-{{ $c->id }}-{{ \Illuminate\Support\Str::slug($label) }}"
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] rounded bg-gray-900 border border-gray-700 text-gray-200 hover:border-amber-400">
                                        <span>{{ $label }}</span><span class="font-mono text-amber-300">{{ $val }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-1">Perícias</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach($c->skills as $skill)
                                    @php $sv = (int) $skill->value + (int) $skill->training_level * 2; @endphp
                                    <button type="button" @click="rollAttr({{ $c->id }}, @js($skill->name), {{ $sv }})"
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] rounded bg-gray-900 border border-gray-700 text-gray-300 hover:border-amber-400">
                                        <span>{{ $skill->name }}</span><span class="font-mono text-amber-300">{{ $sv }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-gray-700">
                            <a href="{{ route('fichas.editar', $c->id) }}" class="text-[10px] text-amber-400 hover:underline">Editar ficha</a>
                            <button type="button" wire:click="removeCombatant({{ $c->id }})" class="text-[10px] text-gray-500 hover:text-red-400">Remover</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-gray-500 text-sm py-6 text-center col-span-full">Nenhum combatente. Adicione fichas de NPC acima.</p>
            @endforelse
        </div>
    </div>

    {{-- ===== Toast de rolagem (canto inferior direito, fora dos cards) ===== --}}
    <div x-show="roll.visible" x-cloak id="combat-roll-toast"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="fixed bottom-6 right-6 z-50 select-none flex flex-col items-end gap-2">

        <div class="bg-gray-800 border border-gray-600 rounded-xl shadow-2xl px-5 py-4 min-w-52">
            {{-- erro --}}
            <template x-if="roll.error">
                <p class="text-xs text-red-400">⚠ <span x-text="roll.error"></span></p>
            </template>

            {{-- atributo/perícia --}}
            <template x-if="!roll.error && roll.kind === 'attr'">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-2">🎲 <span x-text="roll.label"></span></p>
                    <div class="flex items-end gap-2">
                        <div class="text-center">
                            <p class="text-[10px] text-gray-500 mb-0.5">dado</p>
                            <span class="text-2xl font-bold" :class="roll.die === 20 ? 'text-amber-400' : roll.die === 1 ? 'text-red-500' : 'text-white'" x-text="roll.die"></span>
                        </div>
                        <span class="text-gray-500 text-lg mb-0.5">+</span>
                        <div class="text-center">
                            <p class="text-[10px] text-gray-500 mb-0.5">bônus</p>
                            <span class="text-2xl font-bold text-gray-300" x-text="roll.bonus"></span>
                        </div>
                        <span class="text-gray-500 text-lg mb-0.5">=</span>
                        <div class="text-center">
                            <p class="text-[10px] text-gray-500 mb-0.5">total</p>
                            <span class="text-3xl font-black text-amber-400" x-text="roll.total"></span>
                        </div>
                    </div>
                </div>
            </template>

            {{-- habilidade (teste/dano) --}}
            <template x-if="!roll.error && roll.kind === 'jutsu'">
                <div class="min-w-56">
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-2">🌀 <span x-text="roll.name"></span></p>
                    <div class="space-y-2">
                        <template x-for="(line, i) in roll.lines" :key="i">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-[10px] uppercase tracking-widest" :class="line.label === 'Dano' ? 'text-red-400' : 'text-gray-500'" x-text="line.label"></span>
                                <span class="font-mono text-[10px] text-gray-500 flex-1 truncate text-right" x-text="line.expr"></span>
                                <span class="text-2xl font-black flex-shrink-0" :class="line.label === 'Dano' ? 'text-red-400' : 'text-amber-400'" x-text="line.total"></span>
                            </div>
                        </template>
                        <template x-if="!roll.lines.length">
                            <p class="text-[11px] text-gray-500">Usou (sem rolagem).</p>
                        </template>
                    </div>
                </div>
            </template>

            {{-- vantagem/desvantagem aplicadas --}}
            <div x-show="roll.advMods && roll.advMods.length" x-cloak class="mt-2 pt-2 border-t border-gray-700 flex items-center gap-1.5 flex-wrap">
                <span class="text-[10px] text-gray-500 uppercase tracking-widest">1d6</span>
                <template x-for="(m, i) in roll.advMods" :key="i">
                    <span class="w-6 h-6 rounded flex items-center justify-center text-xs font-bold" :class="m.sign > 0 ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'" x-text="(m.sign > 0 ? '+' : '−') + m.value"></span>
                </template>
            </div>
        </div>

        {{-- quadrados d6 de vantagem / desvantagem --}}
        <div class="flex items-center gap-2">
            <button type="button" @click="applyAdvantage(1)" dusk="combat-advantage"
                title="Vantagem: +1d6 ao teste"
                class="w-8 h-8 rounded-md bg-green-600 hover:bg-green-500 text-white font-black flex items-center justify-center ring-1 ring-green-300/40 shadow-[inset_0_1px_0_rgba(255,255,255,0.4),inset_0_-2px_3px_rgba(0,0,0,0.35),0_2px_4px_rgba(0,0,0,0.45)]">6</button>
            <button type="button" @click="applyAdvantage(-1)" dusk="combat-disadvantage"
                title="Desvantagem: −1d6 do teste"
                class="w-8 h-8 rounded-md bg-red-600 hover:bg-red-500 text-white font-black flex items-center justify-center ring-1 ring-red-300/40 shadow-[inset_0_1px_0_rgba(255,255,255,0.4),inset_0_-2px_3px_rgba(0,0,0,0.35),0_2px_4px_rgba(0,0,0,0.45)]">6</button>
            <button type="button" @click="roll.visible = false" title="Fechar"
                class="w-8 h-8 rounded-md bg-gray-700 hover:bg-gray-600 text-gray-300 flex items-center justify-center">✕</button>
        </div>
    </div>
</div>
