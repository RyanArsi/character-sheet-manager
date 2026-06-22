{{--
    Avaliador de notação de dados compartilhado (ficha e combate).
    window.evalDiceExpr(input, statValue) onde statValue(nome) -> número | null
    resolve referências a atributos/perícias do contexto que chamou.
    Retorna { ok:true, total, groups, expr, showBreakdown } ou { ok:false, error }.
--}}
<script>
    window.evalDiceExpr = function (input, statValue) {
        const raw = (input || '').toString().trim().toLowerCase().replace(/\s+/g, '');
        if (!raw) return { ok: false, error: 'Expressão vazia.' };

        if ((raw.match(/\[/g) || []).length !== (raw.match(/\]/g) || []).length) {
            return { ok: false, error: 'Colchetes não fechados. Ex.: d20+[forca].' };
        }

        const signed = (raw[0] === '+' || raw[0] === '-') ? raw : '+' + raw;
        const re = /([+-])(\d*d\d+|\d+|\[[^\]]+\]|[a-zà-ÿ][a-zà-ÿ0-9]*)/g;
        const terms = [];
        let match, consumed = 0;
        while ((match = re.exec(signed)) !== null) {
            if (match.index !== consumed) break;
            terms.push({ sign: match[1], body: match[2] });
            consumed = re.lastIndex;
        }
        if (consumed !== signed.length || terms.length === 0) {
            return { ok: false, error: 'Formato inválido. Ex.: d20+forca, 6d6-2d6+d20-5.' };
        }

        const groups = [];
        let total = 0;
        let diceCount = 0;
        for (const t of terms) {
            const mul = t.sign === '-' ? -1 : 1;
            const isBracket = t.body[0] === '[';
            const isDice = /^\d*d\d+$/.test(t.body);
            const isNumber = /^\d+$/.test(t.body);
            if (isBracket || (!isDice && !isNumber)) {
                const name = (isBracket ? t.body.slice(1, -1) : t.body).trim();
                const val = statValue(name);
                if (val === null || val === undefined) {
                    return { ok: false, error: 'Não encontrei "' + name + '" entre os atributos, especializações ou perícias.' };
                }
                total += mul * val;
                groups.push({ kind: 'ref', sign: t.sign, label: name, value: val });
            } else if (isDice) {
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

        const expr = terms.map((t, i) =>
            i === 0
                ? (t.sign === '-' ? '−' : '') + t.body
                : (t.sign === '-' ? ' − ' : ' + ') + t.body
        ).join('');

        return { ok: true, total, groups, expr, showBreakdown: groups.length > 1 || diceCount > 1 };
    };
</script>
