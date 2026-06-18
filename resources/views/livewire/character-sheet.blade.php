@push('scripts')
<script>
function characterSheet(cid) {
    return {
        cid,
        dirty: false,
        saveTimer: null,
        savedMsg: false,
        restoredMsg: false,

        // Travas de seção
        lockVida: false,
        lockAtributos: false,
        lockEspec: false,
        lockPericias: false,

        // Rolagem de dados
        roll: { kind: 'attr', label: '', die: 0, bonus: 0, total: 0, name: '', lines: [], chakraSpent: 0, visible: false, timer: null },
        rollHistory: [],
        historyOpen: false,

        // Configuração de uso de jutsu (persistida por ficha)
        jutsuCfg: { chakra: true, test: true, damage: true },
        jutsuMedia: null,

        // Alerta de subida de nível
        levelAlert: { visible: false, hp: 0, chakra: '' },

        // Aba ativa da coluna direita
        activeTab: 'equipamentos',

        // Rolador por notação (aba Dados)
        diceInput: '',
        diceError: '',
        diceResult: null,
        diceLog: [],

        rollDice(label, bonus) {
            const die = Math.floor(Math.random() * 20) + 1;
            bonus = parseInt(bonus) || 0;
            const total = die + bonus;
            const now = new Date();
            const time = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

            clearTimeout(this.roll.timer);
            this.roll = {
                kind: 'attr', label, die, bonus, total, visible: true,
                timer: setTimeout(() => { this.roll.visible = false; }, 6000),
            };

            this.rollHistory.unshift({ label, die, bonus, total, time });
            if (this.rollHistory.length > 50) this.rollHistory.pop();
        },

        // Resolve um nome de atributo/especialização/perícia para seu valor.
        // Perícia soma os dois modificadores: valor + (treinamento * 2). Retorna null se não existir.
        statValue(rawName) {
            const norm = s => (s || '').toString().toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')   // remove acentos
                .replace(/\s+/g, '').trim();
            const key = norm(rawName);

            const attrs = ['forca', 'agilidade', 'constituicao', 'inteligencia', 'sabedoria', 'carisma',
                           'ninjutsu', 'genjutsu', 'taijutsu'];
            if (attrs.includes(key)) {
                return parseInt(this.$wire.get(key)) || 0;
            }

            const skills = this.$wire.get('skills') || [];
            for (const s of skills) {
                if (norm(s.name) === key) {
                    return (parseInt(s.value) || 0) + ((parseInt(s.training_level) || 0) * 2);
                }
            }
            return null;
        },

        // Avalia uma expressão de dados (mesma notação da aba Dados, com referências [nome]).
        // Retorna { ok:true, total, groups, expr, showBreakdown } ou { ok:false, error }.
        evalDice(input) {
            const raw = (input || '').toString().trim().toLowerCase().replace(/\s+/g, '');
            if (!raw) return { ok: false, error: 'Expressão vazia.' };

            if ((raw.match(/\[/g) || []).length !== (raw.match(/\]/g) || []).length) {
                return { ok: false, error: 'Colchetes não fechados. Ex.: d20+[forca].' };
            }

            // Garante sinal inicial e quebra em termos: 6d6 | -2d6 | +d20 | +[forca] | -2
            const signed = (raw[0] === '+' || raw[0] === '-') ? raw : '+' + raw;
            const re = /([+-])(\d*d\d+|\d+|\[[^\]]+\])/g;
            const terms = [];
            let match, consumed = 0;
            while ((match = re.exec(signed)) !== null) {
                if (match.index !== consumed) break; // termo malformado/lacuna
                terms.push({ sign: match[1], body: match[2] });
                consumed = re.lastIndex;
            }
            if (consumed !== signed.length || terms.length === 0) {
                return { ok: false, error: 'Formato inválido. Ex.: d20+[forca], 6d6-2d6+d20-5.' };
            }

            const groups = [];
            let total = 0;
            let diceCount = 0;
            for (const t of terms) {
                const mul = t.sign === '-' ? -1 : 1;
                if (t.body[0] === '[') {
                    const name = t.body.slice(1, -1).trim();
                    const val = this.statValue(name);
                    if (val === null) {
                        return { ok: false, error: 'Não encontrei "' + name + '" entre os atributos, especializações ou perícias.' };
                    }
                    total += mul * val;
                    groups.push({ kind: 'ref', sign: t.sign, label: name, value: val });
                } else if (t.body.includes('d')) {
                    const [qPart, sPart] = t.body.split('d');
                    const qty = qPart === '' ? 1 : parseInt(qPart, 10);
                    const sides = parseInt(sPart, 10);
                    if (qty < 1 || qty > 100) {
                        return { ok: false, error: 'Quantidade de dados deve ser entre 1 e 100 (em "' + t.body + '").' };
                    }
                    if (sides < 1 || sides > 1000) {
                        return { ok: false, error: 'O dado deve ter entre 1 e 1000 lados (em "' + t.body + '").' };
                    }
                    const values = [];
                    for (let i = 0; i < qty; i++) {
                        const v = Math.floor(Math.random() * sides) + 1;
                        values.push(v);
                        total += mul * v;
                        diceCount++;
                    }
                    groups.push({ kind: 'dice', sign: t.sign, label: t.body, sides, values });
                } else {
                    const k = parseInt(t.body, 10);
                    total += mul * k;
                    groups.push({ kind: 'const', sign: t.sign, value: k });
                }
            }

            // Expressão normalizada para exibição: 6d6 − 2d6 + d20 − d100 + 2
            const expr = terms.map((t, i) =>
                i === 0
                    ? (t.sign === '-' ? '−' : '') + t.body
                    : (t.sign === '-' ? ' − ' : ' + ') + t.body
            ).join('');

            return { ok: true, total, groups, expr, showBreakdown: groups.length > 1 || diceCount > 1 };
        },

        rollExpression() {
            this.diceError = '';
            const r = this.evalDice(this.diceInput);
            if (!r.ok) {
                this.diceError = r.error;
                return;
            }

            const time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.diceResult = { expr: r.expr, groups: r.groups, total: r.total, time, showBreakdown: r.showBreakdown };
            this.diceLog.unshift(this.diceResult);
            if (this.diceLog.length > 30) this.diceLog.pop();
        },

        // Usa um jutsu: rola teste/dano (toast), desconta chakra e toca a mídia,
        // respeitando os toggles da engrenagem (jutsuCfg).
        playJutsu(j) {
            const lines = [];

            if (this.jutsuCfg.test && j.test) {
                const r = this.evalDice(j.test);
                if (r.ok) lines.push({ label: 'Teste', expr: r.expr, total: r.total });
            }
            if (this.jutsuCfg.damage && j.damage) {
                const r = this.evalDice(j.damage);
                if (r.ok) lines.push({ label: 'Dano', expr: r.expr, total: r.total });
            }

            let chakraSpent = 0;
            if (this.jutsuCfg.chakra && j.chakra != null) {
                const cost = parseInt(j.chakra, 10);
                if (!isNaN(cost) && cost > 0) {
                    chakraSpent = cost;
                    this.$wire.adjustChakra(-cost);
                }
            }

            if (j.media) this.playMedia(j.media, j.volume);

            if (lines.length || chakraSpent) {
                clearTimeout(this.roll.timer);
                this.roll = {
                    kind: 'jutsu', name: j.name, lines, chakraSpent,
                    label: '', die: 0, bonus: 0, total: 0, visible: true,
                    timer: setTimeout(() => { this.roll.visible = false; }, 6000),
                };
            }
        },

        playMedia(url, volume) {
            try {
                if (this.jutsuMedia) { this.jutsuMedia.pause(); }
                const isVideo = /\.(mp4|webm|mov|ogv)$/i.test(url);
                const el = document.createElement(isVideo ? 'video' : 'audio');
                el.src = url;
                el.volume = Math.min(1, Math.max(0, (parseInt(volume, 10) || 100) / 100));
                el.play().catch(() => {});
                this.jutsuMedia = el;
            } catch (e) { /* mídia indisponível — ignora */ }
        },

        init() {
            // Configuração de uso de jutsu, persistida por ficha
            const savedCfg = localStorage.getItem('jutsucfg_' + this.cid);
            if (savedCfg) {
                try { this.jutsuCfg = { ...this.jutsuCfg, ...JSON.parse(savedCfg) }; } catch (e) {}
            }
            this.$watch('jutsuCfg', (v) => {
                localStorage.setItem('jutsucfg_' + this.cid, JSON.stringify(v));
            });

            this.$wire.on('level-up', ({ hp, chakra }) => {
                this.levelAlert = { visible: true, hp, chakra };
            });

            this.$wire.on('sync-storage', (state) => {
                localStorage.setItem('char_' + this.cid, JSON.stringify(state));
                this.dirty = true;
                clearTimeout(this.saveTimer);
                this.saveTimer = setTimeout(() => this.$wire.save(), 30000);
            });

            this.$wire.on('saved', () => {
                this.dirty = false;
                this.savedMsg = true;
                localStorage.removeItem('char_' + this.cid);
                clearTimeout(this.saveTimer);
                setTimeout(() => { this.savedMsg = false; }, 2500);
            });

            this.$nextTick(() => {
                const raw = localStorage.getItem('char_' + this.cid);
                if (!raw) return;
                this.$wire.restoreFromSession(JSON.parse(raw)).then(() => {
                    this.dirty = true;
                    this.restoredMsg = true;
                    setTimeout(() => { this.restoredMsg = false; }, 3000);
                });
            });

            const flushToServer = () => {
                if (!this.dirty) return;
                const raw = localStorage.getItem('char_' + this.cid);
                if (!raw) return;
                fetch('/fichas/' + this.cid + '/autosave', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: raw,
                    keepalive: true,
                }).then(() => {
                    localStorage.removeItem('char_' + this.cid);
                });
            };

            window.addEventListener('beforeunload', flushToServer);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) flushToServer();
            });
        }
    };
}
</script>
@endpush

<div
    class="flex h-screen overflow-hidden"
    x-data="characterSheet({{ $characterId }})"
    x-on:use-jutsu.window="playJutsu($event.detail)"
>

    {{-- ===== COLUNA ESQUERDA (fixa, rolável internamente) ===== --}}
    <aside class="w-72 flex-shrink-0 flex flex-col bg-gray-900 border-r border-gray-700 overflow-y-auto sidebar-scroll">

        {{-- Avatar + Nome --}}
        <div class="flex flex-col items-center gap-2 px-4 pt-5 pb-4 border-b border-gray-700">
            <div class="relative group">
                <div class="w-24 h-24 rounded-full overflow-hidden ring-2 ring-gray-600 bg-gray-800 flex items-center justify-center">
                    @if($avatarPath)
                        <img src="{{ Storage::url($avatarPath) }}" alt="Avatar" class="w-full h-full object-cover">
                    @else
                        <svg class="w-12 h-12 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                        </svg>
                    @endif
                </div>

                <label class="absolute inset-0 rounded-full flex items-center justify-center bg-black/60 opacity-0 group-hover:opacity-100 cursor-pointer transition-opacity">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <input type="file" class="hidden" wire:model="newAvatar" accept="image/*"
                        x-on:change="$wire.uploadAvatar()">
                </label>
            </div>

            <input
                type="text"
                wire:model.live="name"
                placeholder="Nome do Personagem"
                class="w-full bg-transparent text-center text-white font-semibold text-sm border-0 border-b border-transparent focus:border-amber-500 focus:ring-0 focus:outline-none pb-1 placeholder-gray-500"
            >

            {{-- Clã --}}
            <input
                type="text"
                wire:model.live="cla"
                placeholder="Clã"
                class="w-full bg-transparent text-center text-gray-300 text-xs border-0 border-b border-transparent focus:border-amber-500 focus:ring-0 focus:outline-none pb-1 placeholder-gray-500"
            >

            {{-- Nível --}}
            <div class="flex items-center justify-center gap-2 mt-1">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Nível</span>
                <button type="button" wire:click="levelDown"
                    class="w-5 h-5 flex items-center justify-center rounded bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs leading-none">−</button>
                <input
                    type="number"
                    min="1"
                    wire:model.live="level"
                    class="w-12 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-white text-sm font-bold focus:border-amber-500 focus:ring-0 focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                >
                <button type="button" wire:click="levelUp"
                    title="Subir de nível"
                    class="w-5 h-5 flex items-center justify-center rounded bg-amber-600 hover:bg-amber-500 text-white text-xs leading-none">+</button>
            </div>

        </div>

        {{-- Barras de Vida e Chakra --}}
        <div class="px-4 py-4 border-b border-gray-700 space-y-5">

            {{-- Cabeçalho da seção com cadeado --}}
            <div class="flex items-center justify-between -mb-2">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Vida &amp; Chakra</span>
                <button type="button" @click="lockVida = !lockVida"
                    :title="lockVida ? 'Destravar' : 'Travar'"
                    class="text-gray-600 hover:text-gray-300 transition-colors">
                    <svg x-show="!lockVida" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                    </svg>
                    <svg x-show="lockVida" class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </button>
            </div>

            {{-- Vida --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-red-400 uppercase tracking-wider flex items-center gap-1">
                        <span>❤</span> Vida
                    </span>
                    <div class="flex items-center gap-1 text-xs text-gray-400">
                        <span>máx:</span>
                        <input type="number" wire:model.live="hp_max" min="1"
                            :disabled="lockVida"
                            class="w-12 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-gray-400 text-xs focus:border-red-400 focus:ring-0 focus:outline-none disabled:opacity-40 disabled:cursor-not-allowed">
                    </div>
                </div>
                <div class="flex items-center gap-1.5" :class="lockVida ? 'opacity-50' : ''">
                    <button type="button" wire:click="adjustHp(-5)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-red-400 transition-colors px-0.5 disabled:cursor-not-allowed">«</button>
                    <button type="button" wire:click="adjustHp(-1)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-red-400 transition-colors px-0.5 disabled:cursor-not-allowed">‹</button>

                    <div class="relative flex-1 h-8 bg-gray-700 rounded-lg overflow-hidden">
                        <div
                            class="absolute inset-y-0 left-0 bg-gradient-to-r from-red-800 to-red-500 transition-all duration-200"
                            style="width: {{ $hp_max > 0 ? min(100, round(($hp_current / $hp_max) * 100)) : 0 }}%"
                        ></div>
                        <div class="absolute inset-0 flex items-center justify-center gap-0.5">
                            <input type="number" wire:model.live="hp_current" min="0"
                                :disabled="lockVida"
                                class="bar-input w-12 bg-transparent text-center text-white text-sm font-bold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                            <span class="text-white/70 text-xs font-medium">/{{ $hp_max }}</span>
                        </div>
                    </div>

                    <button type="button" wire:click="adjustHp(1)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-red-400 transition-colors px-0.5 disabled:cursor-not-allowed">›</button>
                    <button type="button" wire:click="adjustHp(5)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-red-400 transition-colors px-0.5 disabled:cursor-not-allowed">»</button>
                </div>
            </div>

            {{-- Chakra --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-wider flex items-center gap-1">
                        <span>✦</span> Chakra
                    </span>
                    <div class="flex items-center gap-1 text-xs text-gray-400">
                        <span>máx:</span>
                        <input type="number" wire:model.live="chakra_max" min="1"
                            :disabled="lockVida"
                            class="w-12 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-gray-400 text-xs focus:border-blue-400 focus:ring-0 focus:outline-none disabled:opacity-40 disabled:cursor-not-allowed">
                    </div>
                </div>
                <div class="flex items-center gap-1.5" :class="lockVida ? 'opacity-50' : ''">
                    <button type="button" wire:click="adjustChakra(-5)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-blue-400 transition-colors px-0.5 disabled:cursor-not-allowed">«</button>
                    <button type="button" wire:click="adjustChakra(-1)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-blue-400 transition-colors px-0.5 disabled:cursor-not-allowed">‹</button>

                    <div class="relative flex-1 h-8 bg-gray-700 rounded-lg overflow-hidden">
                        <div
                            class="absolute inset-y-0 left-0 bg-gradient-to-r from-blue-800 to-cyan-500 transition-all duration-200"
                            style="width: {{ $chakra_max > 0 ? min(100, round(($chakra_current / $chakra_max) * 100)) : 0 }}%"
                        ></div>
                        <div class="absolute inset-0 flex items-center justify-center gap-0.5">
                            <input type="number" wire:model.live="chakra_current" min="0"
                                :disabled="lockVida"
                                class="bar-input w-12 bg-transparent text-center text-white text-sm font-bold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                            <span class="text-white/70 text-xs font-medium">/{{ $chakra_max }}</span>
                        </div>
                    </div>

                    <button type="button" wire:click="adjustChakra(1)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-blue-400 transition-colors px-0.5 disabled:cursor-not-allowed">›</button>
                    <button type="button" wire:click="adjustChakra(5)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-blue-400 transition-colors px-0.5 disabled:cursor-not-allowed">»</button>
                </div>
            </div>

        </div>

        {{-- Atributos --}}
        <div class="px-4 py-4 border-b border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold text-amber-500 uppercase tracking-widest">Atributos</h3>
                <button type="button" @click="lockAtributos = !lockAtributos"
                    :title="lockAtributos ? 'Destravar' : 'Travar'"
                    class="text-gray-600 hover:text-gray-300 transition-colors">
                    <svg x-show="!lockAtributos" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                    </svg>
                    <svg x-show="lockAtributos" class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </button>
            </div>
            <div class="space-y-2">
                @foreach([
                    ['forca',        'Força',        'text-orange-400'],
                    ['agilidade',    'Agilidade',     'text-cyan-400'],
                    ['constituicao', 'Constituição',  'text-red-400'],
                    ['inteligencia', 'Inteligência',  'text-purple-400'],
                    ['sabedoria',    'Sabedoria',     'text-green-400'],
                    ['carisma',      'Carisma',       'text-pink-400'],
                ] as [$field, $label, $color])
                <div class="flex items-center justify-between group">
                    <button type="button"
                        @click="rollDice('{{ $label }}', parseInt($wire.{{ $field }}))"
                        dusk="roll-{{ $field }}"
                        class="text-xs {{ $color }} font-medium hover:underline hover:brightness-125 cursor-pointer text-left">
                        {{ $label }}
                    </button>
                    <div class="flex items-center gap-1" :class="lockAtributos ? 'opacity-50' : ''">
                        <button type="button"
                            wire:click="adjustAttr('{{ $field }}', -1)"
                            :disabled="lockAtributos"
                            class="w-5 h-5 flex items-center justify-center rounded bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs leading-none disabled:cursor-not-allowed">−</button>
                        <input type="number" wire:model.live="{{ $field }}" min="0" max="30"
                            :disabled="lockAtributos"
                            class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-white text-sm font-bold focus:border-amber-500 focus:ring-0 focus:outline-none disabled:opacity-40 disabled:cursor-not-allowed">
                        <button type="button"
                            wire:click="adjustAttr('{{ $field }}', 1)"
                            :disabled="lockAtributos"
                            class="w-5 h-5 flex items-center justify-center rounded bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs leading-none disabled:cursor-not-allowed">+</button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Especializações --}}
        <div class="px-4 py-4 border-b border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold text-amber-500 uppercase tracking-widest">Especializações</h3>
                <button type="button" @click="lockEspec = !lockEspec"
                    :title="lockEspec ? 'Destravar' : 'Travar'"
                    class="text-gray-600 hover:text-gray-300 transition-colors">
                    <svg x-show="!lockEspec" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                    </svg>
                    <svg x-show="lockEspec" class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </button>
            </div>
            <div class="space-y-2">
                @foreach([
                    ['ninjutsu', 'Ninjutsu', 'text-blue-400'],
                    ['genjutsu', 'Genjutsu', 'text-red-600'],
                    ['taijutsu', 'Taijutsu', 'text-green-400'],
                ] as [$field, $label, $color])
                <div class="flex items-center justify-between">
                    <button type="button"
                        @click="rollDice('{{ $label }}', parseInt($wire.{{ $field }}))"
                        dusk="roll-{{ $field }}"
                        class="text-xs {{ $color }} font-medium hover:underline hover:brightness-125 cursor-pointer text-left">
                        {{ $label }}
                    </button>
                    <div class="flex items-center gap-1" :class="lockEspec ? 'opacity-50' : ''">
                        <button type="button"
                            wire:click="adjustAttr('{{ $field }}', -1)"
                            :disabled="lockEspec"
                            class="w-5 h-5 flex items-center justify-center rounded bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs leading-none disabled:cursor-not-allowed">−</button>
                        <input type="number" wire:model.live="{{ $field }}" min="0"
                            :disabled="lockEspec"
                            class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-white text-sm font-bold focus:border-amber-500 focus:ring-0 focus:outline-none disabled:opacity-40 disabled:cursor-not-allowed">
                        <button type="button"
                            wire:click="adjustAttr('{{ $field }}', 1)"
                            :disabled="lockEspec"
                            class="w-5 h-5 flex items-center justify-center rounded bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs leading-none disabled:cursor-not-allowed">+</button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Perícias --}}
        <div class="px-4 py-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold text-amber-500 uppercase tracking-widest">Perícias</h3>
                <button type="button" @click="lockPericias = !lockPericias"
                    :title="lockPericias ? 'Destravar' : 'Travar'"
                    class="text-gray-600 hover:text-gray-300 transition-colors">
                    <svg x-show="!lockPericias" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                    </svg>
                    <svg x-show="lockPericias" class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </button>
            </div>
            <div class="space-y-1.5">
                @foreach($skills as $i => $skill)
                @php
                    $lvl = $skill['training_level'] ?? 0;
                    $bonus = $lvl * 2;
                @endphp
                <div class="flex items-center gap-1.5">
                    {{-- Botão de treinamento ciclável --}}
                    <button type="button"
                        wire:click="cycleTraining({{ $i }})"
                        :disabled="lockPericias"
                        title="{{ $lvl === 0 ? 'Sem treinamento' : '+'.($lvl*2) }}"
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

                    {{-- Nome clicável --}}
                    <button type="button"
                        @click="rollDice('{{ $skill['name'] }}', parseInt($wire.skills[{{ $i }}].value) + {{ $bonus }})"
                        class="flex-1 text-xs leading-tight text-left hover:text-white transition-colors cursor-pointer {{ $lvl > 0 ? 'font-semibold text-white' : 'text-gray-300' }}">
                        {{ $skill['name'] }}
                        <span class="text-gray-600 text-[10px]">({{ $skill['attribute'] }})</span>
                    </button>

                    {{-- Valor --}}
                    <input type="number"
                        wire:model.live="skills.{{ $i }}.value"
                        :disabled="lockPericias"
                        class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-white text-xs font-bold focus:border-amber-500 focus:ring-0 focus:outline-none disabled:opacity-40 disabled:cursor-not-allowed">
                </div>
                @endforeach
            </div>
        </div>

        {{-- Espaço no final para respiro ao rolar --}}
        <div class="h-4"></div>
    </aside>

    {{-- ===== COLUNA DO MEIO (controle de turnos, condições, etc) ===== --}}
    <main class="flex-1 min-w-0 overflow-hidden flex flex-col">

        {{-- Barra superior com status de save --}}
        <div class="flex items-center justify-between px-6 py-3 bg-gray-900 border-b border-gray-700 flex-shrink-0">
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-gray-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h1 class="text-sm font-medium text-gray-300">{{ $name ?: 'Ficha de Personagem' }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <span
                    x-show="restoredMsg"
                    x-transition
                    class="text-xs text-amber-400 flex items-center gap-1"
                >
                    <span>⟳</span> Alterações não salvas restauradas
                </span>
                <span
                    x-show="dirty && !savedMsg && !restoredMsg"
                    x-transition
                    class="text-xs text-gray-500 flex items-center gap-1"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse inline-block"></span>
                    Alterações pendentes
                </span>
                <span
                    x-show="savedMsg"
                    x-transition
                    class="text-xs text-green-400 flex items-center gap-1"
                >
                    <span>✓</span> Salvo no servidor
                </span>
                {{-- Histórico de rolagens --}}
                <button type="button" @click="historyOpen = true"
                    dusk="history-btn"
                    class="p-1.5 rounded bg-gray-700 hover:bg-gray-600 text-gray-300 hover:text-white transition-colors relative"
                    title="Histórico de rolagens">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
                    </svg>
                </button>

                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    class="px-3 py-1.5 text-xs font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="save">Salvar</span>
                    <span wire:loading wire:target="save">Salvando…</span>
                </button>
            </div>
        </div>

        {{-- Miolo central — controle de turnos, condições, etc --}}
        <div class="flex-1 overflow-y-auto flex items-center justify-center text-gray-700 text-sm">
            Controle de turnos &amp; condições — em construção
        </div>

    </main>

    {{-- ===== COLUNA DIREITA (abas: equipamentos, talentos, jutsus, notas) ===== --}}
    <aside class="w-[28rem] flex-shrink-0 flex flex-col bg-gray-800 border-l border-gray-700 overflow-hidden">

        {{-- Abas (guias estilo navegador) --}}
        <div class="flex items-end gap-1 px-4 pt-2 bg-gray-900 border-b border-gray-700 flex-shrink-0">
            @foreach([
                ['equipamentos', 'Equipamentos'],
                ['talentos',     'Talentos'],
                ['jutsus',       'Jutsus'],
                ['dados',        'Dados'],
                ['notas',        'Notas'],
            ] as [$tab, $label])
            <button type="button"
                @click="activeTab = '{{ $tab }}'"
                dusk="tab-{{ $tab }}"
                :class="activeTab === '{{ $tab }}'
                    ? 'bg-gray-800 text-amber-400 border-gray-700 border-b-gray-800'
                    : 'bg-gray-900 text-gray-500 hover:text-gray-300 border-transparent'"
                class="px-4 py-2 text-xs font-medium rounded-t-lg border border-b-0 -mb-px transition-colors">
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- Conteúdo da aba ativa --}}
        <div class="flex-1 overflow-y-auto p-6">
            {{-- Equipamentos --}}
            <div x-show="activeTab === 'equipamentos'" dusk="panel-equipamentos">
                <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest mb-4">Equipamentos</h2>
                <p class="text-gray-600 text-sm">Conteúdo em construção.</p>
            </div>

            {{-- Talentos --}}
            <div x-show="activeTab === 'talentos'" dusk="panel-talentos" x-cloak>
                <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest mb-4">Talentos</h2>
                <p class="text-gray-600 text-sm">Conteúdo em construção.</p>
            </div>

            {{-- Jutsus --}}
            <div x-show="activeTab === 'jutsus'" dusk="panel-jutsus" x-cloak>
                <livewire:jutsu-panel :character-id="$characterId" :key="'jutsu-panel-'.$characterId" />
            </div>

            {{-- Dados --}}
            <div x-show="activeTab === 'dados'" dusk="panel-dados" x-cloak>
                <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest mb-4">Dados</h2>

                {{-- Entrada de notação --}}
                <form @submit.prevent="rollExpression()" class="mb-4">
                    <div class="flex gap-2">
                        <input type="text"
                            x-model="diceInput"
                            dusk="dice-input"
                            placeholder="ex: d20+[forca], 6d6-2d6+d20-5"
                            autocomplete="off" spellcheck="false"
                            class="flex-1 bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 font-mono lowercase focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none">
                        <button type="submit"
                            dusk="dice-roll-btn"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-gray-900 text-sm font-bold rounded-lg transition-colors flex-shrink-0">
                            🎲 Rolar
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-600 mt-1.5 leading-relaxed">
                        Combine termos com <span class="font-mono text-gray-500">+</span> e <span class="font-mono text-gray-500">−</span>, e some valores da ficha com <span class="font-mono text-gray-500">[nome]</span>:
                        <span class="font-mono text-gray-500">d20+[forca]</span>,
                        <span class="font-mono text-gray-500">d6+[taijutsu]</span>,
                        <span class="font-mono text-gray-500">2d6+[esquiva]-1</span>
                    </p>
                    <p x-show="diceError" x-text="diceError" x-cloak
                        dusk="dice-error"
                        class="text-xs text-red-400 mt-2"></p>
                </form>

                {{-- Resultado --}}
                <template x-if="diceResult">
                    <div dusk="dice-result" class="bg-gray-900 border border-gray-700 rounded-xl p-4 mb-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="font-mono text-xs text-amber-400 tracking-wider" x-text="diceResult.expr"></span>
                            <span class="text-3xl font-black text-white leading-none" dusk="dice-total" x-text="diceResult.total"></span>
                        </div>

                        {{-- Detalhamento por termo --}}
                        <div class="space-y-2" x-show="diceResult.showBreakdown">
                            <template x-for="(g, gi) in diceResult.groups" :key="gi">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <template x-if="g.kind === 'dice'">
                                        <span class="font-mono text-[10px] w-14 flex-shrink-0"
                                            :class="g.sign === '-' ? 'text-red-400' : 'text-gray-500'"
                                            x-text="(g.sign === '-' ? '−' : '+') + g.label"></span>
                                    </template>
                                    <template x-if="g.kind === 'dice'">
                                        <template x-for="(v, i) in g.values" :key="i">
                                            <span class="w-8 h-8 rounded-md text-xs font-bold flex items-center justify-center"
                                                :class="g.sign === '-'
                                                    ? 'bg-red-500/10 text-red-300'
                                                    : (v === g.sides ? 'bg-amber-500/30 text-amber-300' : v === 1 ? 'bg-red-500/20 text-red-300' : 'bg-gray-700 text-white')"
                                                x-text="v"></span>
                                        </template>
                                    </template>
                                    <template x-if="g.kind === 'const'">
                                        <span class="font-mono text-[11px] px-2 py-1 rounded-md bg-gray-800"
                                            :class="g.sign === '-' ? 'text-red-400' : 'text-gray-400'"
                                            x-text="(g.sign === '-' ? '−' : '+') + g.value"></span>
                                    </template>
                                    <template x-if="g.kind === 'ref'">
                                        <span class="font-mono text-[11px] px-2 py-1 rounded-md bg-amber-500/10"
                                            :class="g.sign === '-' ? 'text-red-300' : 'text-amber-300'"
                                            x-text="(g.sign === '-' ? '−' : '+') + g.label + ' (' + g.value + ')'"></span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Histórico local da aba --}}
                <div x-show="diceLog.length > 0" x-cloak>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Recentes</h3>
                        <button type="button" @click="diceLog = []" dusk="dice-clear-btn"
                            class="text-[10px] text-gray-600 hover:text-red-400 transition-colors">Limpar</button>
                    </div>
                    <div class="space-y-1.5">
                        <template x-for="(r, i) in diceLog" :key="i">
                            <div class="flex items-center gap-3 bg-gray-900/60 rounded-lg px-3 py-2">
                                <span class="font-mono text-[11px] text-gray-400 flex-1 truncate" x-text="r.expr"></span>
                                <span class="text-[10px] text-gray-600 flex-shrink-0" x-text="r.time"></span>
                                <span class="text-sm font-bold text-amber-400 flex-shrink-0 w-10 text-right" x-text="r.total"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Notas --}}
            <div x-show="activeTab === 'notas'" dusk="panel-notas" x-cloak>
                <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest mb-4">Notas</h2>
                <p class="text-gray-600 text-sm">Conteúdo em construção.</p>
            </div>
        </div>

    </aside>

    {{-- Painel lateral de histórico --}}
    <div x-show="historyOpen" id="roll-history-drawer" class="fixed inset-0 z-40 flex justify-end" x-cloak>
        {{-- Overlay --}}
        <div class="absolute inset-0 bg-black/50" @click="historyOpen = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">
        </div>

        {{-- Drawer --}}
        <div class="relative w-80 bg-gray-900 border-l border-gray-700 flex flex-col h-full shadow-2xl"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700 flex-shrink-0">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                    🎲 Histórico de Rolagens
                </h2>
                <div class="flex items-center gap-2">
                    <button type="button" @click="rollHistory = []"
                        x-show="rollHistory.length > 0"
                        class="text-[10px] text-gray-500 hover:text-red-400 transition-colors">
                        Limpar
                    </button>
                    <button type="button" @click="historyOpen = false"
                        dusk="history-close-btn"
                        class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Lista --}}
            <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2">
                <template x-if="rollHistory.length === 0">
                    <p class="text-gray-600 text-sm text-center mt-8">Nenhuma rolagem ainda.<br>Clique em um atributo, especialização ou perícia.</p>
                </template>

                <template x-for="(r, i) in rollHistory" :key="i">
                    <div class="flex items-center gap-3 bg-gray-800 rounded-lg px-3 py-2.5"
                        :class="r.die === 20 ? 'ring-1 ring-amber-500/50' : r.die === 1 ? 'ring-1 ring-red-500/50' : ''">

                        {{-- Dado --}}
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 font-black text-sm"
                            :class="r.die === 20 ? 'bg-amber-500/20 text-amber-400' : r.die === 1 ? 'bg-red-500/20 text-red-400' : 'bg-gray-700 text-white'"
                            x-text="r.die">
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-white truncate" x-text="r.label"></p>
                            <p class="text-[10px] text-gray-500">
                                <span x-text="r.die"></span> + <span x-text="r.bonus"></span> = <span class="text-amber-400 font-bold" x-text="r.total"></span>
                                <span x-show="r.die === 20" class="text-amber-400 ml-1">✦ crítico!</span>
                                <span x-show="r.die === 1" class="text-red-400 ml-1">✦ falha!</span>
                            </p>
                        </div>

                        {{-- Hora --}}
                        <span class="text-[10px] text-gray-600 flex-shrink-0" x-text="r.time"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Alerta de subida de nível --}}
    <div x-show="levelAlert.visible" id="level-up-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>
        {{-- Overlay --}}
        <div class="absolute inset-0 bg-black/60" @click="levelAlert.visible = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">
        </div>

        {{-- Caixa --}}
        <div class="relative w-[30%] h-[50%] flex flex-col bg-gray-800 border border-amber-500/40 rounded-xl shadow-2xl px-6 py-5"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95">

            <h2 class="text-base font-bold text-amber-400 flex items-center gap-2">
                ⬆ Subiu de nível!
            </h2>

            <div class="flex-1 flex flex-col justify-center space-y-2 text-sm text-gray-200">
                <p class="flex items-center gap-2">
                    <span>❤</span>
                    <span>Aumento na vida: <span class="font-bold text-red-400" x-text="levelAlert.hp"></span></span>
                </p>
                <p class="flex items-center gap-2">
                    <span>✦</span>
                    <span>Aumento no chakra: <span class="font-bold text-blue-400" x-text="levelAlert.chakra"></span></span>
                </p>
            </div>

            <div class="flex justify-end">
                <button type="button" @click="levelAlert.visible = false"
                    dusk="level-up-close"
                    class="px-3 py-1.5 text-xs font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                    Entendi
                </button>
            </div>
        </div>
    </div>

    {{-- Painel de rolagem — canto inferior direito --}}
    <div
        x-show="roll.visible"
        id="roll-toast"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        class="fixed bottom-6 right-6 z-50 select-none"
        @click="roll.visible = false"
    >
        <div class="bg-gray-800 border border-gray-600 rounded-xl shadow-2xl px-5 py-4 min-w-48 cursor-pointer">
            {{-- Rolagem de atributo/perícia (d20 + bônus) --}}
            <template x-if="roll.kind === 'attr'">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-1">
                        🎲 <span x-text="roll.label"></span>
                    </p>
                    <div class="flex items-end gap-2">
                        <div class="text-center">
                            <p class="text-[10px] text-gray-500 mb-0.5">dado</p>
                            <span class="text-2xl font-bold"
                                :class="roll.die === 20 ? 'text-amber-400' : roll.die === 1 ? 'text-red-500' : 'text-white'"
                                x-text="roll.die"></span>
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

            {{-- Uso de jutsu (teste/dano + chakra) --}}
            <template x-if="roll.kind === 'jutsu'">
                <div class="min-w-56" dusk="jutsu-toast">
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-1">
                        🌀 <span x-text="roll.name"></span>
                    </p>
                    <div class="space-y-1.5">
                        <template x-for="(line, i) in roll.lines" :key="i">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-[10px] uppercase tracking-widest"
                                    :class="line.label === 'Dano' ? 'text-red-400' : 'text-gray-500'"
                                    x-text="line.label"></span>
                                <span class="font-mono text-[10px] text-gray-500 flex-1 truncate text-right" x-text="line.expr"></span>
                                <span class="text-2xl font-black flex-shrink-0"
                                    :class="line.label === 'Dano' ? 'text-red-400' : 'text-amber-400'"
                                    x-text="line.total"></span>
                            </div>
                        </template>
                        <p x-show="roll.chakraSpent > 0" class="text-[11px] text-blue-400 pt-1 border-t border-gray-700">
                            chakra <span class="font-bold" x-text="'−' + roll.chakraSpent"></span>
                        </p>
                    </div>
                </div>
            </template>

            <p class="text-[10px] text-gray-600 mt-2 text-right">clique para fechar</p>
        </div>
    </div>

</div>
