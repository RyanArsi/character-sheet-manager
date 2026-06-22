<div>
    {{-- ============ LISTA (notas da ficha) ============ --}}
    @if($view === 'list')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest">Notas</h2>
            <button type="button" wire:click="startCreate"
                dusk="note-create"
                class="px-2.5 py-1 text-[11px] font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                + Adicionar nota
            </button>
        </div>

        <div class="space-y-2">
            @forelse($notes as $note)
                <button type="button" wire:click="startEdit({{ $note->id }})"
                    dusk="note-card"
                    class="w-full text-left bg-gray-900 border border-gray-700 rounded-lg px-3 py-2.5 hover:border-amber-500/60 hover:bg-gray-900/60 transition-colors group">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-amber-300 truncate group-hover:text-amber-200">{{ $note->title }}</h3>
                        <span class="text-[10px] text-gray-600 flex-shrink-0">{{ $note->updated_at?->format('d/m/Y') }}</span>
                    </div>
                    @if(filled($note->body))
                        <p class="text-xs text-gray-400 mt-1 line-clamp-2 break-words whitespace-pre-line">{{ \Illuminate\Support\Str::limit($note->body, 180) }}</p>
                    @else
                        <p class="text-xs text-gray-600 italic mt-1">Sem conteúdo</p>
                    @endif
                </button>
            @empty
                <p class="text-gray-600 text-sm">Nenhuma nota ainda. Clique em <span class="text-gray-400">+ Adicionar nota</span> para criar a primeira.</p>
            @endforelse
        </div>
    @endif

    {{-- ============ FORMULÁRIO (criar / editar / ver) ============ --}}
    @if($view === 'form')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-amber-500 uppercase tracking-widest">
                {{ $editingId ? 'Editar nota' : 'Nova nota' }}
            </h2>
            <button type="button" wire:click="cancelForm"
                dusk="note-cancel"
                class="px-2.5 py-1 text-[11px] font-medium rounded bg-gray-700 hover:bg-gray-600 text-gray-200 transition-colors">
                ← Voltar
            </button>
        </div>

        <form wire:submit="save" class="space-y-3">
            {{-- Título --}}
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Título</label>
                <input type="text" wire:model="title" dusk="note-title" autofocus
                    class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm focus:border-amber-500 focus:ring-0 focus:outline-none">
                @error('title') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Conteúdo (sem limite) --}}
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Conteúdo</label>
                <textarea wire:model="body" dusk="note-body" rows="16"
                    placeholder="Escreva o que quiser, sem limite de tamanho…"
                    class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-white text-sm leading-relaxed focus:border-amber-500 focus:ring-0 focus:outline-none resize-y"></textarea>
                @error('body') <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between pt-1">
                @if($editingId)
                    <button type="button" wire:click="deleteNote({{ $editingId }})"
                        wire:confirm="Excluir esta nota?"
                        dusk="note-delete"
                        class="text-[11px] text-red-500 hover:text-red-400">Excluir</button>
                @else
                    <span></span>
                @endif
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    dusk="note-save"
                    class="px-4 py-1.5 text-xs font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition-colors disabled:opacity-50">
                    Salvar
                </button>
            </div>
        </form>
    @endif
</div>
