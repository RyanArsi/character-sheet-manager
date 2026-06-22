{{--
    Formata a mensagem de um evento do feed da campanha, colorindo:
    - valor + palavra "chakra" → azul
    - "teste" + valor          → verde
    - "dano" + valor           → vermelho
    - nome da habilidade pelo tipo (detail.type): jutsu → azul, teste → verde, talento → laranja
    Recebe a mensagem já como texto puro e o `detail` do evento; escapa HTML antes
    de injetar os <span>, então é seguro usar com x-html mesmo com nomes de jogador.
    `dark` = true para o feed escuro da ficha; false para o feed claro da campanha.
--}}
<script>
    window.fmtCampaignEvent = function (message, detail, dark) {
        const esc = (s) => String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        const P = dark
            ? { blue: 'text-blue-400', green: 'text-green-400', red: 'text-red-400', orange: 'text-orange-400' }
            : { blue: 'text-blue-600', green: 'text-green-600', red: 'text-red-600', orange: 'text-orange-600' };

        const wrap = (cls, inner) => '<span class="font-semibold ' + cls + '">' + inner + '</span>';

        let s = esc(message);

        // Nome da habilidade colorido pelo tipo
        if (detail && detail.name && detail.type) {
            const cls = { jutsu: P.blue, test: P.green, talent: P.orange }[detail.type];
            if (cls) {
                const name = esc(detail.name);
                s = s.replace(name, wrap(cls, name));
            }
        }

        // Palavras-chave com seus valores
        s = s.replace(/([−-]?\s*\d+\s*chakra)/gi, (m) => wrap(P.blue, m));
        s = s.replace(/(teste\s*\d+)/gi, (m) => wrap(P.green, m));
        s = s.replace(/(dano\s*\d+)/gi, (m) => wrap(P.red, m));

        return s;
    };
</script>
