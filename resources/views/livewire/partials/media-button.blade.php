{{--
    Botão dedicado para tocar o som/vídeo de um jutsu, talento ou equipamento.
    Desvinculado do clique no nome — dispara o evento play-media, ouvido pela ficha.
    $url    : URL pública da mídia (já passada por Storage::url)
    $volume : volume 0–100
--}}
<button type="button"
    @click="$dispatch('play-media', { url: @js($url), volume: @js($volume) })"
    title="Tocar som"
    class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded border border-gray-600 text-gray-400 hover:text-amber-300 hover:border-amber-400 transition-colors">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
        <path d="M11 5 6 9H2v6h4l5 4V5z"/>
        <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
        <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
    </svg>
</button>
