{{--
    Links marcados com data-return guardam a tela atual (URL + aba via hash)
    no parâmetro ?from=, para a ficha conseguir "voltar" exatamente para onde
    o usuário estava. Incluído nas páginas que têm links de "editar ficha".
--}}
<script>
    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[data-return]');
        if (! a) return;
        e.preventDefault();
        const base = a.getAttribute('href');
        const sep = base.includes('?') ? '&' : '?';
        window.location.href = base + sep + 'from=' + encodeURIComponent(window.location.href);
    });
</script>
