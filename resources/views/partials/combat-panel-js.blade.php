{{--
    Lógica Alpine da aba Combate. Definida no nível da página (não dentro do
    componente Livewire) para não ser redefinida a cada render.
    Usa window.evalDiceExpr (partials/dice-roller).
--}}
<script>
    window.combatPanel = function (data) {
        return {
            data: data || [],
            expandedId: null,
            activeSub: {},      // id -> 'habilidades' | 'status'
            results: {},        // id -> texto do último resultado
            diceSound: null,

            toggle(id) { this.expandedId = this.expandedId === id ? null : id; },
            sub(id) { return this.activeSub[id] || 'habilidades'; },
            setSub(id, t) { this.activeSub[id] = t; },

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

            // Resolve nome -> valor (atributo/especialização/perícia) deste combatente.
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

            setResult(id, text) { this.results = { ...this.results, [id]: text }; },
            findC(id) { return this.data.find(x => x.id === id); },
            roll(id, name, test, damage) { const c = this.findC(id); if (c) this.rollAbility(c, name, test, damage); },
            rollAttr(id, label, value) { const c = this.findC(id); if (c) this.rollStat(c, label, value); },

            // Rola teste/dano de uma habilidade (jutsu/talento/ação/equip/teste).
            rollAbility(c, name, test, damage) {
                const lines = [];
                for (const [label, expr] of [['Teste', test], ['Dano', damage]]) {
                    if (! expr) continue;
                    const r = window.evalDiceExpr(expr, (n) => this.statValue(c, n));
                    if (! r.ok) { this.setResult(c.id, '⚠ ' + r.error); return; }
                    lines.push(label + ' ' + r.total);
                }
                if (! lines.length) return;
                this.playDiceSound();
                this.setResult(c.id, name + ' — ' + lines.join(', '));
                this.$wire.shareRoll(c.id, 'usou ' + name + ' — ' + lines.join(', '), { kind: 'jutsu', name });
            },

            // Rola d20 + valor de um atributo/perícia.
            rollStat(c, label, value) {
                value = parseInt(value) || 0;
                const die = Math.floor(Math.random() * 20) + 1;
                const total = die + value;
                const sb = value ? (value > 0 ? ' +' + value : ' ' + value) : '';
                this.playDiceSound();
                const text = label + ' → ' + total + ' (d20: ' + die + sb + ')';
                this.setResult(c.id, text);
                this.$wire.shareRoll(c.id, text, { kind: 'attr', label, die, bonus: value, total });
            },
        };
    };
</script>
