{{--
    Grade de cards de ficha (NPC) do mestre.
    $list   : Collection de Character (NPC sheets)
    $groups : Collection de CampaignSheetGroup (para o seletor de grupo)
--}}
@if($list->isEmpty())
    <p class="text-gray-600 text-xs py-2">Nenhuma ficha aqui.</p>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($list as $sheet)
            <div class="bg-gray-900 border border-gray-700 rounded-lg p-3" dusk="sheet-card-{{ $sheet->id }}">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($sheet->avatar)
                            <img src="{{ Storage::url($sheet->avatar) }}" alt="" class="w-full h-full object-cover">
                        @else
                            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                            </svg>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-100 truncate">{{ $sheet->name ?: 'Sem nome' }}</p>
                        <p class="text-xs text-gray-500">Nível {{ $sheet->level }}@if($sheet->village) · {{ $sheet->village }}@endif</p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 mt-3">
                    <a href="{{ route('fichas.editar', $sheet->id) }}" data-return
                        class="text-xs text-amber-400 hover:underline">Abrir / editar</a>

                    <div class="flex items-center gap-2">
                        <select dusk="sheet-move-{{ $sheet->id }}"
                            x-on:change="$wire.moveSheet({{ $sheet->id }}, $event.target.value ? Number($event.target.value) : null)"
                            class="bg-gray-800 border border-gray-700 rounded text-[11px] text-gray-300 px-1.5 py-1 focus:border-amber-500 focus:ring-0 focus:outline-none max-w-[7rem]">
                            <option value="" @selected($sheet->sheet_group_id === null)>Sem grupo</option>
                            @foreach($groups as $g)
                                <option value="{{ $g->id }}" @selected($sheet->sheet_group_id === $g->id)>{{ $g->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="deleteSheet({{ $sheet->id }})"
                            wire:confirm="Excluir esta ficha? Não dá para desfazer."
                            title="Excluir ficha" class="text-xs text-gray-500 hover:text-red-400">✕</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
