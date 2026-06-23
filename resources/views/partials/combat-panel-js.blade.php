{{--
    Lógica Alpine da aba Combate (nível de página). Usa window.evalDiceExpr.
    O resultado aparece num toast fixo no canto inferior direito (fora dos cards),
    com os quadrados d6 de vantagem/desvantagem.
--}}
<script>
    window.combatPanel = function (data, events) {
        return {
            data: data || [],
            feed: events || [],
            expandedId: null,
            activeSub: {},      // id -> 'habilidades' | 'status'
            diceSound: null,
            jutsuMedia: null,

            roll: {
                visible: false, kind: 'attr', name: '', label: '',
                die: 0, bonus: 0, total: 0, lines: [], advMods: [],
                cId: null, error: '', timer: null,
            },

            // Eventos e iniciativa ao vivo (assinatura única que sobrevive aos re-renders).
            initEcho(cid) {
                if (! window.Echo) return;
                window.Echo.private('campaign.' + cid)
                    .listen('.CampaignEventBroadcast', (e) => {
                        this.feed.unshift(e);
                        if (this.feed.length > 100) this.feed.pop();
                    })
                    .listen('.CampaignInitiativeUpdated', () => { this.$wire.$refresh(); });
            },

            toggle(id) { this.expandedId = this.expandedId === id ? null : id; },
            sub(id) { return this.activeSub[id] || 'habilidades'; },
            setSub(id, t) { this.activeSub[id] = t; },

            findC(id) { return this.data.find(x => x.id === id); },
            roll_(id, type, name, test, damage) { const c = this.findC(id); if (c) this.rollAbility(c, type, name, test, damage); },
            rollAttr(id, label, value) { const c = this.findC(id); if (c) this.rollStat(c, label, value); },

            playDiceSound() {
                try {
                    if (! this.diceSound) {
                        this.diceSound = new Audio(@js(asset('audio/dice-roll-Cris.mp3')));
                        this.diceSound.volume = 0.7;
                    }
                    this.diceSound.currentTime = 0;
                    this.diceSound.play().catch(() => {});
                } catch (e) {}
            },

            // Toca a mídia do jutsu localmente e transmite para a campanha (só a URL).
            playMedia(url, volume) {
                if (! url) return;
                try {
                    if (this.jutsuMedia) this.jutsuMedia.pause();
                    const isVideo = /\.(mp4|webm|mov|ogv)$/i.test(url);
                    const el = document.createElement(isVideo ? 'video' : 'audio');
                    el.src = url;
                    el.volume = Math.min(1, Math.max(0, (parseInt(volume, 10) || 100) / 100));
                    el.play().catch(() => {});
                    this.jutsuMedia = el;
                } catch (e) {}
                this.$wire.shareMedia(url, parseInt(volume, 10) || 100);
            },

            statValue(c, rawName) {
                const norm = s => (s || '').toString().toLowerCase()
                    .normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, '').trim();
                const key = norm(rawName);
                if (c.attrs && key in c.attrs) return parseInt(c.attrs[key]) || 0;
                for (const s of (c.skills || [])) {
                    if (norm(s.name) === key) {
                        return (parseInt(s.value) || 0) + ((parseInt(s.training_level) || 0) * 2);
                    }
                }
                return null;
            },

            showToast() {
                clearTimeout(this.roll.timer);
                this.roll.visible = true;
                this.roll.timer = setTimeout(() => { this.roll.visible = false; }, 8000);
            },

            rollAbility(c, type, name, test, damage) {
                const lines = [];
                let error = '';
                for (const [label, expr] of [['Teste', test], ['Dano', damage]]) {
                    if (! expr) continue;
                    const r = window.evalDiceExpr(expr, (n) => this.statValue(c, n));
                    if (! r.ok) { error = r.error; break; }
                    lines.push({ label, expr: r.expr, total: r.total, groups: r.groups });
                }
                this.playDiceSound();
                this.roll = { visible: true, kind: 'jutsu', name, label: '', die: 0, bonus: 0,
                    total: 0, lines, advMods: [], cId: c.id, error, timer: null };
                this.showToast();
                if (error) return;
                const parts = lines.map((l) => l.label + ' ' + l.total);
                const suffix = parts.length ? ' — ' + parts.join(', ') : '';
                // type (jutsu/talent/action/equipment/test) colore o nome no feed
                this.$wire.shareRoll(c.id, 'usou ' + name + suffix, { kind: 'jutsu', type, name });
            },

            rollStat(c, label, value) {
                value = parseInt(value) || 0;
                const die = Math.floor(Math.random() * 20) + 1;
                const total = die + value;
                this.playDiceSound();
                this.roll = { visible: true, kind: 'attr', name: '', label, die, bonus: value,
                    total, lines: [], advMods: [], cId: c.id, error: '', timer: null };
                this.showToast();
                const sb = value ? (value > 0 ? ' +' + value : ' ' + value) : '';
                this.$wire.shareRoll(c.id, label + ' → ' + total + ' (d20: ' + die + sb + ')',
                    { kind: 'attr', label, die, bonus: value, total });
            },

            applyAdvantage(sign) {
                const r = this.roll;
                const hasRoll = (r.kind === 'attr' && r.die > 0)
                    || (r.kind === 'jutsu' && Array.isArray(r.lines) && r.lines.length > 0);
                if (! hasRoll || r.error) return;
                this.playDiceSound();
                const d6 = Math.floor(Math.random() * 6) + 1;
                if (! Array.isArray(r.advMods)) r.advMods = [];
                r.advMods.push({ sign, value: d6 });
                const advSum = r.advMods.reduce((s, m) => s + m.sign * m.value, 0);
                if (r.kind === 'attr') {
                    r.total = r.die + r.bonus + advSum;
                } else {
                    const line = r.lines.find((l) => l.label === 'Teste') || r.lines[0];
                    if (line) { if (line.base === undefined) line.base = line.total; line.total = line.base + advSum; }
                }
                this.showToast();
                const newTotal = r.kind === 'attr' ? r.total
                    : (r.lines.find((l) => l.label === 'Teste') || r.lines[0]).total;
                if (r.cId) this.$wire.shareRoll(r.cId,
                    (sign > 0 ? 'Vantagem' : 'Desvantagem') + ' +1d6 (' + d6 + ') → ' + newTotal,
                    { kind: 'adv', sign, d6, total: newTotal });
            },
        };
    };
</script>
