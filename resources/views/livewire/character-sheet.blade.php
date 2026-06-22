@push('scripts')
@include('partials.campaign-event-format')
@include('partials.dice-roller')
<script>
function characterSheet(cid, campaigns) {
    return {
        cid,
        campaigns: campaigns || [],
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

        // Som de rolagem de dados (tocado em toda rolagem)
        diceSound: null,

        // Acumulador de modificações das barras (vida/chakra).
        // Mostra um número amarelo somando os ajustes; zera após 7s sem mexer na mesma barra.
        barDelta: {
            vida:   { value: 0, visible: false, timer: null },
            chakra: { value: 0, visible: false, timer: null },
        },

        // Alerta de subida de nível
        levelAlert: { visible: false, hp: 0, chakra: '' },

        // Aba ativa da coluna direita
        activeTab: 'equipamentos',

        // Rolador por notação (aba Dados)
        diceInput: '',
        diceError: '',
        diceResult: null,
        diceLog: [],

        // Compartilhamento de eventos com a campanha (feed ao vivo)
        shareCampaignId: '',   // '' = não compartilhar
        feed: [],
        feedChannel: null,

        // Iniciativa compartilhada da campanha
        initiative: { entries: [], current_id: null, round: 1, conditions: [] },
        initMod: 0,            // modificador opcional do jogador na iniciativa
        npcName: '',
        npcRoll: 10,
        condName: '',
        condTarget: '',
        condTurns: 1,

        rollDice(label, bonus) {
            this.playDiceSound();
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

            const sb = bonus ? (bonus > 0 ? ' +' + bonus : ' ' + bonus) : '';
            this.shareRoll(label + ' → ' + total + ' (d20: ' + die + sb + ')',
                { kind: 'attr', label, die, bonus, total });
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

        // Avalia uma expressão de dados (mesma notação da aba Dados). Valores da ficha
        // podem ser referenciados pelo nome direto (ex.: d20+forca) — colchetes [forca]
        // continuam aceitos para compatibilidade e para nomes com números/símbolos.
        // Retorna { ok:true, total, groups, expr, showBreakdown } ou { ok:false, error }.
        evalDice(input) {
            // Avaliador compartilhado (partials/dice-roller); resolve nomes pela ficha.
            return window.evalDiceExpr(input, (name) => this.statValue(name));
        },

        rollExpression() {
            this.diceError = '';
            const r = this.evalDice(this.diceInput);
            if (!r.ok) {
                this.diceError = r.error;
                return;
            }
            this.playDiceSound();

            const time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.diceResult = { expr: r.expr, groups: r.groups, total: r.total, time, showBreakdown: r.showBreakdown };
            this.diceLog.unshift(this.diceResult);
            if (this.diceLog.length > 30) this.diceLog.pop();

            this.shareRoll('rolou ' + r.expr + ' → ' + r.total,
                { kind: 'expr', expr: r.expr, total: r.total });
        },

        // Usa um jutsu: rola teste/dano (toast) e desconta chakra,
        // respeitando os toggles da engrenagem (jutsuCfg).
        // O som é tocado à parte, pelo botão dedicado (evento play-media).
        playJutsu(j) {
            const lines = [];

            if (this.jutsuCfg.test && j.test) {
                const r = this.evalDice(j.test);
                if (r.ok) lines.push({ label: 'Teste', expr: r.expr, total: r.total, groups: r.groups });
            }
            if (this.jutsuCfg.damage && j.damage) {
                const r = this.evalDice(j.damage);
                if (r.ok) lines.push({ label: 'Dano', expr: r.expr, total: r.total, groups: r.groups });
            }

            let chakraSpent = 0;
            if (this.jutsuCfg.chakra && j.chakra != null) {
                const cost = parseInt(j.chakra, 10);
                if (!isNaN(cost) && cost > 0) {
                    chakraSpent = cost;
                    this.$wire.adjustChakra(-cost);
                    this.bumpDelta('chakra', -cost);
                }
            }

            if (lines.length) this.playDiceSound();

            if (lines.length || chakraSpent) {
                clearTimeout(this.roll.timer);
                this.roll = {
                    kind: 'jutsu', name: j.name, lines, chakraSpent,
                    label: '', die: 0, bonus: 0, total: 0, visible: true,
                    timer: setTimeout(() => { this.roll.visible = false; }, 6000),
                };

                const parts = lines.map((l) => l.label + ' ' + l.total);
                if (chakraSpent) parts.push('−' + chakraSpent + ' chakra');
                const suffix = parts.length ? ' — ' + parts.join(', ') : '';
                this.shareRoll('usou ' + j.name + suffix,
                    { kind: 'jutsu', type: j.type, name: j.name, lines, chakraSpent });
            }
        },

        // Toca o som de rolagem de dados. Reaproveita o mesmo elemento de áudio
        // e reinicia do começo para suportar rolagens em sequência.
        playDiceSound() {
            try {
                if (! this.diceSound) {
                    this.diceSound = new Audio(@js(asset('audio/dice-roll-Cris.mp3')));
                    this.diceSound.volume = 0.7;
                }
                this.diceSound.currentTime = 0;
                this.diceSound.play().catch(() => {});
            } catch (e) { /* áudio indisponível — ignora */ }
        },

        // Acumula a modificação de uma barra e exibe o total (amarelo) embaixo dela.
        // Cada novo ajuste soma ao valor atual e renova o tempo; após 7s sem mexer
        // na mesma barra, o acumulador zera e some.
        bumpDelta(bar, amount) {
            const d = this.barDelta[bar];
            if (! d) return;
            clearTimeout(d.timer);
            d.value += amount;
            d.visible = true;
            d.timer = setTimeout(() => { d.value = 0; d.visible = false; }, 5000);
        },

        // Disparado pelo botão de som dos cards. Se a ficha está compartilhando
        // com uma campanha, manda todo mundo (inclusive eu) tocar via WebSocket —
        // trafega só a URL, cada cliente busca o arquivo do servidor de assets.
        // Sem campanha, toca apenas localmente.
        playMedia(url, volume) {
            const id = parseInt(this.shareCampaignId);
            if (id) {
                this.$wire.shareMedia(id, url, parseInt(volume, 10) || 100);
                return;
            }
            this.playMediaLocal(url, volume);
        },

        // Reprodução local de fato (não retransmite — usada por mim e por quem
        // recebe o broadcast de mídia da campanha).
        playMediaLocal(url, volume) {
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

        // Vantagem (+1) / Desvantagem (-1): rola 1d6 e soma/subtrai ao teste exibido.
        applyAdvantage(sign) {
            const r = this.roll;
            const hasRoll = (r.kind === 'attr' && r.die > 0)
                || (r.kind === 'jutsu' && Array.isArray(r.lines) && r.lines.length > 0);
            if (!hasRoll) return;
            this.playDiceSound();

            const d6 = Math.floor(Math.random() * 6) + 1;
            if (! Array.isArray(r.advMods)) r.advMods = [];
            r.advMods.push({ sign, value: d6 });
            const advSum = r.advMods.reduce((s, m) => s + m.sign * m.value, 0);

            if (r.kind === 'attr') {
                r.total = r.die + r.bonus + advSum;
            } else {
                // aplica ao teste (linha "Teste"; senão à primeira linha)
                const line = r.lines.find((l) => l.label === 'Teste') || r.lines[0];
                if (line) {
                    if (line.base === undefined) line.base = line.total;
                    line.total = line.base + advSum;
                }
            }

            // mantém o toast visível e renova o tempo
            clearTimeout(r.timer);
            r.visible = true;
            r.timer = setTimeout(() => { this.roll.visible = false; }, 6000);

            const newTotal = r.kind === 'attr'
                ? r.total
                : (r.lines.find((l) => l.label === 'Teste') || r.lines[0]).total;
            this.shareRoll((sign > 0 ? 'Vantagem' : 'Desvantagem') + ' +1d6 (' + d6 + ') → ' + newTotal,
                { kind: 'adv', sign, d6, total: newTotal });
        },

        // Envia um evento ao servidor, que grava e transmite para a campanha selecionada.
        shareRoll(message, detail) {
            const id = parseInt(this.shareCampaignId);
            if (!id) return;
            this.$wire.shareEvent(id, message, detail || {});
        },

        // (Re)assina o canal privado da campanha escolhida para receber o feed ao vivo.
        subscribeFeed() {
            if (this.feedChannel) {
                window.Echo.leave('campaign.' + this.feedChannel);
                this.feedChannel = null;
            }
            this.feed = [];
            this.initiative = { entries: [], current_id: null, round: 1, conditions: [] };
            const id = parseInt(this.shareCampaignId);
            if (!id || !window.Echo) return;

            this.feedChannel = id;
            window.Echo.private('campaign.' + id)
                .listen('.CampaignEventBroadcast', (e) => {
                    this.feed.unshift(e);
                    if (this.feed.length > 100) this.feed.pop();
                })
                .listen('.CampaignInitiativeUpdated', (e) => {
                    if (e.state && parseInt(this.shareCampaignId) === id) this.initiative = e.state;
                })
                .listen('.CampaignMediaBroadcast', (e) => {
                    // Alguém na campanha usou uma mídia: toca aqui também (só a URL veio).
                    if (e.url) this.playMediaLocal(e.url, e.volume);
                });

            // Carrega o estado atual da iniciativa ao entrar na campanha
            this.$wire.getInitiative(id).then((st) => {
                if (st && parseInt(this.shareCampaignId) === id) this.initiative = st;
            });
        },

        // ---- Iniciativa ----
        get isMaster() {
            const c = this.campaigns.find((x) => x.id === parseInt(this.shareCampaignId));
            return !!(c && c.is_master);
        },

        rollInitiative() {
            this.playDiceSound();
            const agi = parseInt(this.$wire.get('agilidade')) || 0;
            const mod = parseInt(this.initMod) || 0;
            const die = Math.floor(Math.random() * 20) + 1;
            const total = die + agi + mod;

            clearTimeout(this.roll.timer);
            this.roll = {
                kind: 'attr', label: 'Iniciativa', die, bonus: agi + mod, total, visible: true,
                timer: setTimeout(() => { this.roll.visible = false; }, 6000),
            };

            const id = parseInt(this.shareCampaignId);
            if (!id) return;
            const modTxt = mod ? (mod > 0 ? ' +' + mod : ' ' + mod) : '';
            this.shareRoll('Iniciativa → ' + total + ' (d20: ' + die + ' +' + agi + ' agi' + modTxt + ')',
                { kind: 'init', die, agi, mod, total });
            this.$wire.addInitiativeEntry(id, total).then((st) => { if (st) this.initiative = st; });
        },

        passTurn() {
            const id = parseInt(this.shareCampaignId);
            if (!id || !this.isMaster) return;
            this.$wire.passTurn(id).then((st) => { if (st) this.initiative = st; });
        },

        addNpcEntry() {
            const id = parseInt(this.shareCampaignId);
            if (!id || !this.isMaster || !this.npcName.trim()) return;
            this.$wire.addNpc(id, this.npcName.trim(), parseInt(this.npcRoll) || 0).then((st) => {
                if (st) this.initiative = st;
                this.npcName = ''; this.npcRoll = 10;
            });
        },

        removeInitEntry(entryId) {
            const id = parseInt(this.shareCampaignId);
            if (!id) return;
            this.$wire.removeEntry(id, entryId).then((st) => { if (st) this.initiative = st; });
        },

        clearInit() {
            const id = parseInt(this.shareCampaignId);
            if (!id || !this.isMaster) return;
            if (!confirm('Limpar a iniciativa e zerar as rodadas?')) return;
            this.$wire.clearInitiative(id).then((st) => { if (st) this.initiative = st; });
        },

        addCond() {
            const id = parseInt(this.shareCampaignId);
            if (!id || !this.condName.trim() || !this.condTarget) return;
            this.$wire.addCondition(id, this.condName.trim(), this.condTarget, parseInt(this.condTurns) || 1).then((st) => {
                if (st) this.initiative = st;
                this.condName = ''; this.condTarget = ''; this.condTurns = 1;
            });
        },

        removeCond(condId) {
            const id = parseInt(this.shareCampaignId);
            if (!id) return;
            this.$wire.removeCondition(id, condId).then((st) => { if (st) this.initiative = st; });
        },

        entryConditions(entryId) {
            return (this.initiative.conditions || []).filter((c) => c.target_id === entryId);
        },

        entryName(entryId) {
            const e = (this.initiative.entries || []).find((x) => x.id === entryId);
            return e ? e.name : '—';
        },

        init() {
            // Campanha selecionada para compartilhar, persistida por ficha
            const savedShare = localStorage.getItem('share_' + this.cid);
            if (savedShare) this.shareCampaignId = savedShare;
            this.$watch('shareCampaignId', (v) => {
                localStorage.setItem('share_' + this.cid, v ?? '');
                this.subscribeFeed();
            });
            this.subscribeFeed();

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
    x-data="characterSheet({{ $characterId }}, @js($campaignOptions))"
    x-on:use-jutsu.window="playJutsu($event.detail)"
    x-on:play-media.window="playMedia($event.detail.url, $event.detail.volume)"
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
                    <button type="button" wire:click="adjustHp(-5)" @click="bumpDelta('vida', -5)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-red-400 transition-colors px-0.5 disabled:cursor-not-allowed">«</button>
                    <button type="button" wire:click="adjustHp(-1)" @click="bumpDelta('vida', -1)"
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

                    <button type="button" wire:click="adjustHp(1)" @click="bumpDelta('vida', 1)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-red-400 transition-colors px-0.5 disabled:cursor-not-allowed">›</button>
                    <button type="button" wire:click="adjustHp(5)" @click="bumpDelta('vida', 5)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-red-400 transition-colors px-0.5 disabled:cursor-not-allowed">»</button>
                </div>
                <div class="h-4 mt-0.5 text-center leading-none">
                    <span x-show="barDelta.vida.visible" x-cloak x-transition.opacity
                        class="text-[11px] font-bold text-amber-400"
                        x-text="'(' + (barDelta.vida.value >= 0 ? '+' : '') + barDelta.vida.value + ')'"></span>
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
                    <button type="button" wire:click="adjustChakra(-5)" @click="bumpDelta('chakra', -5)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-blue-400 transition-colors px-0.5 disabled:cursor-not-allowed">«</button>
                    <button type="button" wire:click="adjustChakra(-1)" @click="bumpDelta('chakra', -1)"
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

                    <button type="button" wire:click="adjustChakra(1)" @click="bumpDelta('chakra', 1)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-blue-400 transition-colors px-0.5 disabled:cursor-not-allowed">›</button>
                    <button type="button" wire:click="adjustChakra(5)" @click="bumpDelta('chakra', 5)"
                        :disabled="lockVida"
                        class="text-xs font-bold text-gray-400 hover:text-blue-400 transition-colors px-0.5 disabled:cursor-not-allowed">»</button>
                </div>
                <div class="h-4 mt-0.5 text-center leading-none">
                    <span x-show="barDelta.chakra.visible" x-cloak x-transition.opacity
                        class="text-[11px] font-bold text-amber-400"
                        x-text="'(' + (barDelta.chakra.value >= 0 ? '+' : '') + barDelta.chakra.value + ')'"></span>
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

        {{-- Vantagem / Desvantagem — ajusta o teste exibido com ±1d6 --}}
        <div class="flex items-center gap-2 px-6 py-2 bg-gray-900/40 border-b border-gray-700 flex-shrink-0">
            <button type="button" @click="applyAdvantage(1)" dusk="advantage-btn"
                title="Vantagem: rola 1d6 e soma ao teste exibido"
                class="w-7 h-7 rounded-md bg-green-600 hover:bg-green-500 active:translate-y-px text-white font-black text-sm flex items-center justify-center ring-1 ring-green-300/40 transition-all shadow-[inset_0_1px_0_rgba(255,255,255,0.4),inset_0_-2px_3px_rgba(0,0,0,0.35),0_2px_4px_rgba(0,0,0,0.45)]">6</button>
            <button type="button" @click="applyAdvantage(-1)" dusk="disadvantage-btn"
                title="Desvantagem: rola 1d6 e subtrai do teste exibido"
                class="w-7 h-7 rounded-md bg-red-600 hover:bg-red-500 active:translate-y-px text-white font-black text-sm flex items-center justify-center ring-1 ring-red-300/40 transition-all shadow-[inset_0_1px_0_rgba(255,255,255,0.4),inset_0_-2px_3px_rgba(0,0,0,0.35),0_2px_4px_rgba(0,0,0,0.45)]">6</button>

            {{-- Iniciativa: d20 + agilidade + modificador opcional --}}
            <div class="flex items-center gap-1.5 pl-2 ml-1 border-l border-gray-700">
                <button type="button" @click="rollInitiative()" dusk="initiative-btn"
                    title="Rolar iniciativa (d20 + agilidade + modificador). Entra na ordem da campanha selecionada."
                    class="px-2.5 h-7 rounded-md bg-indigo-600 hover:bg-indigo-500 active:translate-y-px text-white text-xs font-bold flex items-center gap-1 ring-1 ring-indigo-300/40 transition-all shadow-[inset_0_1px_0_rgba(255,255,255,0.3),0_2px_4px_rgba(0,0,0,0.4)]">
                    ⚡ Iniciativa
                </button>
                <input type="number" x-model.number="initMod" title="Modificador de iniciativa"
                    class="w-12 h-7 text-center bg-gray-800 border border-gray-700 rounded text-xs text-gray-200 focus:border-indigo-400 focus:ring-0 focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
            </div>
        </div>

        {{-- Miolo central — iniciativa/turnos (esquerda) + feed da campanha (direita, 30%) --}}
        <div class="flex-1 min-h-0 flex">

          {{-- ===== Painel de iniciativa, turnos e condições ===== --}}
          <div class="flex-1 min-w-0 flex flex-col overflow-y-auto sidebar-scroll p-4">

            <template x-if="!shareCampaignId">
                <div class="m-auto text-center text-gray-600 text-xs px-6">
                    Selecione uma campanha no painel à direita para usar a iniciativa compartilhada.
                </div>
            </template>

            <template x-if="shareCampaignId">
              <div class="space-y-4">

                {{-- Cabeçalho: rodada + controles do mestre --}}
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold text-amber-500 uppercase tracking-widest">Iniciativa</h3>
                        <span class="text-[11px] text-gray-300 bg-gray-800 rounded-full px-2 py-0.5">Rodada <span class="font-bold" x-text="initiative.round"></span></span>
                    </div>
                    <div class="flex items-center gap-1.5" x-show="isMaster" x-cloak>
                        <button type="button" @click="passTurn()" dusk="pass-turn-btn"
                            class="px-2.5 h-7 rounded-md bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold">Passar turno ▸</button>
                        <button type="button" @click="clearInit()" title="Limpar iniciativa e zerar rodadas"
                            class="px-2 h-7 rounded-md bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs">Limpar</button>
                    </div>
                </div>

                {{-- Ordem da iniciativa --}}
                <div class="space-y-1.5">
                    <template x-if="!initiative.entries.length">
                        <p class="text-xs text-gray-600">Ninguém na iniciativa ainda. Use o botão “⚡ Iniciativa” acima.</p>
                    </template>
                    <template x-for="(e, i) in initiative.entries" :key="e.id">
                        <div class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 border transition-colors"
                            :class="e.id === initiative.current_id ? 'border-red-500/60 bg-red-500/10' : 'border-gray-700 bg-gray-800/50'">
                            {{-- bolinha vermelha = turno atual --}}
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                :class="e.id === initiative.current_id ? 'bg-red-500 animate-pulse' : 'bg-gray-700'"></span>
                            <span class="text-[10px] text-gray-500 w-4 text-right flex-shrink-0" x-text="i + 1"></span>
                            <span class="text-sm text-gray-100 truncate" x-text="e.name"></span>
                            <span x-show="e.is_npc" class="text-[9px] uppercase font-bold text-rose-300 bg-rose-900/40 rounded px-1 py-0.5 flex-shrink-0">NPC</span>
                            {{-- indicador de condições no participante --}}
                            <template x-for="c in entryConditions(e.id)" :key="c.id">
                                <span class="text-[9px] text-purple-200 bg-purple-900/50 rounded px-1 py-0.5 flex-shrink-0"
                                    :title="c.name + ' — ' + c.turns_left + ' turno(s)'">
                                    <span x-text="c.name"></span><span class="opacity-60" x-text="' ' + c.turns_left"></span>
                                </span>
                            </template>
                            <span class="flex-1"></span>
                            <span class="text-sm font-bold text-amber-400 w-7 text-right flex-shrink-0" x-text="e.roll"></span>
                            <button type="button" @click="removeInitEntry(e.id)"
                                x-show="isMaster || e.user_id === {{ auth()->id() }}"
                                title="Remover da iniciativa"
                                class="text-gray-600 hover:text-red-400 text-xs flex-shrink-0">✕</button>
                        </div>
                    </template>
                </div>

                {{-- Mestre: adicionar NPC --}}
                <div x-show="isMaster" x-cloak class="flex items-center gap-1.5">
                    <input type="text" x-model="npcName" placeholder="Nome do NPC" @keydown.enter="addNpcEntry()"
                        class="flex-1 min-w-0 bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 focus:border-amber-500 focus:ring-0 focus:outline-none">
                    <input type="number" x-model.number="npcRoll" title="Valor de iniciativa do NPC"
                        class="w-14 bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 text-center [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                    <button type="button" @click="addNpcEntry()"
                        class="px-2.5 h-7 rounded-md bg-rose-700 hover:bg-rose-600 text-white text-xs font-semibold whitespace-nowrap">+ NPC</button>
                </div>

                {{-- Condições & eventos --}}
                <div class="pt-3 border-t border-gray-700">
                    <h3 class="text-xs font-bold text-amber-500 uppercase tracking-widest mb-2">Condições &amp; Eventos</h3>

                    <div class="flex items-center gap-1.5 mb-2">
                        <input type="text" x-model="condName" placeholder="Nome da condição" @keydown.enter="addCond()"
                            class="flex-1 min-w-0 bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 focus:border-amber-500 focus:ring-0 focus:outline-none">
                        <select x-model="condTarget"
                            class="bg-gray-800 border border-gray-700 rounded px-1 py-1 text-xs text-gray-200 max-w-[8rem] focus:border-amber-500 focus:ring-0 focus:outline-none">
                            <option value="">está em…</option>
                            <template x-for="e in initiative.entries" :key="e.id">
                                <option :value="e.id" x-text="e.name"></option>
                            </template>
                        </select>
                        <input type="number" min="1" x-model.number="condTurns" title="Turnos para acabar"
                            class="w-12 bg-gray-800 border border-gray-700 rounded px-1 py-1 text-xs text-gray-200 text-center [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                        <button type="button" @click="addCond()"
                            class="px-2.5 h-7 rounded-md bg-purple-700 hover:bg-purple-600 text-white text-xs font-bold">+</button>
                    </div>

                    <template x-if="!initiative.conditions.length">
                        <p class="text-xs text-gray-600">Nenhuma condição ativa.</p>
                    </template>
                    <div class="space-y-1">
                        <template x-for="c in initiative.conditions" :key="c.id">
                            <div class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 bg-purple-900/20 border border-purple-800/40">
                                <span class="text-xs font-semibold text-purple-200 truncate" x-text="c.name"></span>
                                <span class="text-[11px] text-gray-500">em</span>
                                <span class="text-xs text-gray-200 truncate flex-1" x-text="entryName(c.target_id)"></span>
                                <span class="text-[11px] text-purple-300 whitespace-nowrap" x-text="c.turns_left + ' turno(s)'"></span>
                                <button type="button" @click="removeCond(c.id)"
                                    class="text-gray-600 hover:text-red-400 text-xs flex-shrink-0">✕</button>
                            </div>
                        </template>
                    </div>
                </div>

              </div>
            </template>

          </div>

          {{-- ===== Painel do feed da campanha (30%) ===== --}}
          <div class="w-[30%] min-w-[16rem] flex flex-col border-l border-gray-700 bg-gray-900/20">

            {{-- Seletor: em qual campanha compartilhar os eventos --}}
            <div class="flex items-center gap-2 px-4 py-2.5 bg-gray-900/40 border-b border-gray-700 flex-shrink-0">
                <svg class="w-4 h-4 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 12h16M4 19h10"/>
                </svg>
                <span class="text-xs text-gray-400 whitespace-nowrap">Compartilhar em:</span>
                <select x-model="shareCampaignId" dusk="share-campaign"
                    class="flex-1 min-w-0 bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 focus:border-amber-500 focus:ring-0 focus:outline-none">
                    <option value="">Não compartilhar</option>
                    @foreach($campaignOptions as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                    @endforeach
                </select>
                <span x-show="shareCampaignId" x-cloak
                    class="flex items-center gap-1 text-[10px] text-green-400 whitespace-nowrap" title="Transmitindo ao vivo">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> ao vivo
                </span>
            </div>

            {{-- Feed ao vivo --}}
            <div class="flex-1 min-h-0 overflow-y-auto px-4 py-3 space-y-2 sidebar-scroll">
                <template x-if="!feed.length">
                    <div class="text-center text-gray-600 text-xs pt-12 px-6"
                        x-text="shareCampaignId
                            ? 'Conectado. As rolagens e habilidades usadas aparecerão aqui para toda a campanha.'
                            : @js($campaignOptions ? 'Selecione uma campanha acima para ver e compartilhar os eventos de dados.' : 'Esta ficha não está em nenhuma campanha.')"></div>
                </template>
                <template x-for="(ev, i) in feed" :key="ev.id ?? i">
                    <div class="bg-gray-800/70 border border-gray-700 rounded-lg px-3 py-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-amber-400 truncate" x-text="ev.actor"></span>
                            <span class="text-[10px] text-gray-500 flex-shrink-0" x-text="ev.time"></span>
                        </div>
                        <div class="text-xs text-gray-200 mt-0.5 break-words" x-html="fmtCampaignEvent(ev.message, ev.detail, true)"></div>
                    </div>
                </template>
            </div>

          </div>
        </div>

    </main>

    {{-- ===== COLUNA DIREITA (abas: equipamentos, talentos, jutsus, notas) ===== --}}
    <aside class="w-[28rem] flex-shrink-0 flex flex-col bg-gray-800 border-l border-gray-700 overflow-hidden">

        {{-- Abas (guias estilo navegador) --}}
        <div class="flex items-end gap-0.5 px-2 pt-2 bg-gray-900 border-b border-gray-700 flex-shrink-0">
            @foreach([
                ['equipamentos', 'Equip.'],
                ['talentos',     'Talentos'],
                ['jutsus',       'Jutsus'],
                ['acoes',        'Ações'],
                ['testes',       'Testes'],
                ['dados',        'Dados'],
                ['notas',        'Notas'],
            ] as [$tab, $label])
            <button type="button"
                @click="activeTab = '{{ $tab }}'"
                dusk="tab-{{ $tab }}"
                :class="activeTab === '{{ $tab }}'
                    ? 'bg-gray-800 text-amber-400 border-gray-700 border-b-gray-800'
                    : 'bg-gray-900 text-gray-500 hover:text-gray-300 border-transparent'"
                class="px-2 py-2 text-[11px] font-medium rounded-t-lg border border-b-0 -mb-px transition-colors whitespace-nowrap">
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- Conteúdo da aba ativa --}}
        <div class="flex-1 overflow-y-auto p-6">
            {{-- Equipamentos --}}
            <div x-show="activeTab === 'equipamentos'" dusk="panel-equipamentos">
                <livewire:equipment-panel :character-id="$characterId" :key="'equipment-panel-'.$characterId" />
            </div>

            {{-- Talentos --}}
            <div x-show="activeTab === 'talentos'" dusk="panel-talentos" x-cloak>
                <livewire:talent-panel :character-id="$characterId" :key="'talent-panel-'.$characterId" />
            </div>

            {{-- Jutsus --}}
            <div x-show="activeTab === 'jutsus'" dusk="panel-jutsus" x-cloak>
                <livewire:jutsu-panel :character-id="$characterId" :key="'jutsu-panel-'.$characterId" />
            </div>

            {{-- Ações --}}
            <div x-show="activeTab === 'acoes'" dusk="panel-acoes" x-cloak>
                <livewire:action-panel :character-id="$characterId" :key="'action-panel-'.$characterId" />
            </div>

            {{-- Testes --}}
            <div x-show="activeTab === 'testes'" dusk="panel-testes" x-cloak>
                <livewire:test-panel :character-id="$characterId" :key="'test-panel-'.$characterId" />
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
                            placeholder="ex: d20+forca, 6d6-2d6+d20-5"
                            autocomplete="off" spellcheck="false"
                            class="flex-1 bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 font-mono lowercase focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none">
                        <button type="submit"
                            dusk="dice-roll-btn"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-gray-900 text-sm font-bold rounded-lg transition-colors flex-shrink-0">
                            🎲 Rolar
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-600 mt-1.5 leading-relaxed">
                        Combine termos com <span class="font-mono text-gray-500">+</span> e <span class="font-mono text-gray-500">−</span>, e some valores da ficha pelo <span class="text-gray-500">nome</span>:
                        <span class="font-mono text-gray-500">d20+forca</span>,
                        <span class="font-mono text-gray-500">d6+taijutsu</span>,
                        <span class="font-mono text-gray-500">2d6+esquiva-1</span>
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
                <livewire:note-panel :character-id="$characterId" :key="'note-panel-'.$characterId" />
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
                    <div class="space-y-2">
                        <template x-for="(line, i) in roll.lines" :key="i">
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-[10px] uppercase tracking-widest"
                                        :class="line.label === 'Dano' ? 'text-red-400' : 'text-gray-500'"
                                        x-text="line.label"></span>
                                    <span class="font-mono text-[10px] text-gray-500 flex-1 truncate text-right" x-text="line.expr"></span>
                                    <span class="text-2xl font-black flex-shrink-0"
                                        :class="line.label === 'Dano' ? 'text-red-400' : 'text-amber-400'"
                                        x-text="line.total"></span>
                                </div>
                                {{-- Dados individuais (verde = máx, vermelho = mín) + modificadores --}}
                                <div class="flex items-center gap-1 flex-wrap mt-1" x-show="line.groups && line.groups.length">
                                    <template x-for="(g, gi) in line.groups" :key="gi">
                                        <div class="flex items-center gap-1 flex-wrap">
                                            <template x-if="g.kind === 'dice'">
                                                <template x-for="(v, vi) in g.values" :key="vi">
                                                    <span class="w-6 h-6 rounded text-[11px] font-bold flex items-center justify-center"
                                                        :class="g.sign === '-'
                                                            ? 'bg-red-500/10 text-red-300 ring-1 ring-red-500/30'
                                                            : (v === g.sides ? 'bg-green-500/25 text-green-300 ring-1 ring-green-400/50' : v === 1 ? 'bg-red-500/25 text-red-300 ring-1 ring-red-400/50' : 'bg-gray-700 text-white')"
                                                        x-text="v"></span>
                                                </template>
                                            </template>
                                            <template x-if="g.kind === 'const'">
                                                <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-gray-800"
                                                    :class="g.sign === '-' ? 'text-red-400' : 'text-gray-400'"
                                                    x-text="(g.sign === '-' ? '−' : '+') + g.value"></span>
                                            </template>
                                            <template x-if="g.kind === 'ref'">
                                                <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-amber-500/10"
                                                    :class="g.sign === '-' ? 'text-red-300' : 'text-amber-300'"
                                                    x-text="(g.sign === '-' ? '−' : '+') + g.label + ' (' + g.value + ')'"></span>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <p x-show="roll.chakraSpent > 0" class="text-[11px] text-blue-400 pt-1 border-t border-gray-700">
                            chakra <span class="font-bold" x-text="'−' + roll.chakraSpent"></span>
                        </p>
                    </div>
                </div>
            </template>

            {{-- Vantagem / desvantagem aplicadas (±1d6) --}}
            <div x-show="roll.advMods && roll.advMods.length" x-cloak
                dusk="adv-chips"
                class="mt-2 pt-2 border-t border-gray-700 flex items-center gap-1.5 flex-wrap">
                <span class="text-[10px] text-gray-500 uppercase tracking-widest">1d6</span>
                <template x-for="(m, i) in roll.advMods" :key="i">
                    <span class="w-6 h-6 rounded flex items-center justify-center text-xs font-bold"
                        :class="m.sign > 0 ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'"
                        x-text="(m.sign > 0 ? '+' : '−') + m.value"></span>
                </template>
            </div>

            <p class="text-[10px] text-gray-600 mt-2 text-right">clique para fechar</p>
        </div>
    </div>

</div>
