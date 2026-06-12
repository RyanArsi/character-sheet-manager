<div
    class="flex h-screen overflow-hidden"
    x-data="{
        dirty: false,
        saveTimer: null,
        savedMsg: false,
        init() {
            $wire.on('saved', () => {
                this.dirty = false;
                this.savedMsg = true;
                setTimeout(() => this.savedMsg = false, 2000);
            });
        },
        markDirty() {
            this.dirty = true;
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => $wire.save(), 30000);
        }
    }"
    @input="markDirty()"
    @change="markDirty()"
>

    {{-- ===== COLUNA ESQUERDA (fixa, rolável internamente) ===== --}}
    <aside class="w-72 flex-shrink-0 flex flex-col bg-gray-900 border-r border-gray-700 overflow-y-auto">

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
                wire:model="name"
                placeholder="Nome do Personagem"
                class="w-full bg-transparent text-center text-white font-semibold text-sm border-0 border-b border-transparent focus:border-amber-500 focus:ring-0 focus:outline-none pb-1 placeholder-gray-500"
            >

            <div class="flex gap-2 w-full">
                <input type="text" wire:model="race" placeholder="Raça"
                    class="w-1/2 bg-gray-800 text-xs text-gray-300 rounded px-2 py-1 border border-gray-700 focus:border-amber-500 focus:ring-0 focus:outline-none placeholder-gray-600">
                <input type="text" wire:model="village" placeholder="Aldeia"
                    class="w-1/2 bg-gray-800 text-xs text-gray-300 rounded px-2 py-1 border border-gray-700 focus:border-amber-500 focus:ring-0 focus:outline-none placeholder-gray-600">
            </div>
        </div>

        {{-- Barras de Vida e Chakra --}}
        <div class="px-4 py-4 border-b border-gray-700 space-y-4">

            {{-- Vida --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-bold text-red-400 uppercase tracking-wider flex items-center gap-1">
                        <span>❤</span> Vida
                    </span>
                    <div class="flex items-center gap-1 text-xs text-gray-300">
                        <input type="number" wire:model="hp_current" min="0" :max="hp_max"
                            class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-white focus:border-red-400 focus:ring-0 focus:outline-none">
                        <span class="text-gray-500">/</span>
                        <input type="number" wire:model="hp_max" min="1"
                            class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-gray-400 focus:border-red-400 focus:ring-0 focus:outline-none">
                    </div>
                </div>
                <div class="h-2.5 bg-gray-700 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-red-700 to-red-500 rounded-full transition-all duration-300"
                        style="width: {{ $hp_max > 0 ? min(100, round(($hp_current / $hp_max) * 100)) : 0 }}%"
                    ></div>
                </div>
            </div>

            {{-- Chakra --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-wider flex items-center gap-1">
                        <span>✦</span> Chakra
                    </span>
                    <div class="flex items-center gap-1 text-xs text-gray-300">
                        <input type="number" wire:model="chakra_current" min="0" :max="chakra_max"
                            class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-white focus:border-blue-400 focus:ring-0 focus:outline-none">
                        <span class="text-gray-500">/</span>
                        <input type="number" wire:model="chakra_max" min="1"
                            class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-gray-400 focus:border-blue-400 focus:ring-0 focus:outline-none">
                    </div>
                </div>
                <div class="h-2.5 bg-gray-700 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-blue-700 to-cyan-500 rounded-full transition-all duration-300"
                        style="width: {{ $chakra_max > 0 ? min(100, round(($chakra_current / $chakra_max) * 100)) : 0 }}%"
                    ></div>
                </div>
            </div>

        </div>

        {{-- Atributos --}}
        <div class="px-4 py-4 border-b border-gray-700">
            <h3 class="text-xs font-bold text-amber-500 uppercase tracking-widest mb-3">Atributos</h3>
            <div class="space-y-2">
                @foreach([
                    ['forca',        'Força',        'text-orange-400'],
                    ['agilidade',    'Agilidade',     'text-green-400'],
                    ['constituicao', 'Constituição',  'text-red-400'],
                    ['inteligencia', 'Inteligência',  'text-purple-400'],
                    ['sabedoria',    'Sabedoria',     'text-cyan-400'],
                    ['carisma',      'Carisma',       'text-pink-400'],
                ] as [$field, $label, $color])
                <div class="flex items-center justify-between">
                    <span class="text-xs {{ $color }} font-medium">{{ $label }}</span>
                    <div class="flex items-center gap-1">
                        <button type="button"
                            wire:click="$set('{{ $field }}', max(1, {{ $$field }} - 1))"
                            class="w-5 h-5 flex items-center justify-center rounded bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs leading-none">−</button>
                        <input type="number" wire:model="{{ $field }}" min="1" max="30"
                            class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-white text-sm font-bold focus:border-amber-500 focus:ring-0 focus:outline-none">
                        <button type="button"
                            wire:click="$set('{{ $field }}', min(30, {{ $$field }} + 1))"
                            class="w-5 h-5 flex items-center justify-center rounded bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs leading-none">+</button>
                        <span class="text-xs text-gray-500 w-8 text-right">
                            {{ ($mod = intdiv($$field - 10, 2)) >= 0 ? "+$mod" : $mod }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Especializações --}}
        <div class="px-4 py-4 border-b border-gray-700">
            <h3 class="text-xs font-bold text-amber-500 uppercase tracking-widest mb-3">Especializações</h3>
            <div class="space-y-2">
                @foreach([
                    ['ninjutsu', 'Ninjutsu', 'text-blue-400'],
                    ['genjutsu', 'Genjutsu', 'text-purple-400'],
                    ['taijutsu', 'Taijutsu', 'text-orange-400'],
                ] as [$field, $label, $color])
                <div class="flex items-center justify-between">
                    <span class="text-xs {{ $color }} font-medium">{{ $label }}</span>
                    <div class="flex items-center gap-1">
                        <button type="button"
                            wire:click="$set('{{ $field }}', max(0, {{ $$field }} - 1))"
                            class="w-5 h-5 flex items-center justify-center rounded bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs leading-none">−</button>
                        <input type="number" wire:model="{{ $field }}" min="0"
                            class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-white text-sm font-bold focus:border-amber-500 focus:ring-0 focus:outline-none">
                        <button type="button"
                            wire:click="$set('{{ $field }}', {{ $$field }} + 1)"
                            class="w-5 h-5 flex items-center justify-center rounded bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs leading-none">+</button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Perícias --}}
        <div class="px-4 py-4">
            <h3 class="text-xs font-bold text-amber-500 uppercase tracking-widest mb-3">Perícias</h3>
            <div class="space-y-1.5">
                @foreach($skills as $i => $skill)
                <div class="flex items-center gap-1.5">
                    {{-- Checkbox treinado --}}
                    <input type="checkbox"
                        wire:model="skills.{{ $i }}.trained"
                        class="w-3.5 h-3.5 rounded border-gray-600 bg-gray-800 text-amber-500 focus:ring-0 focus:ring-offset-0 cursor-pointer flex-shrink-0">

                    {{-- Nome + atributo --}}
                    <span class="flex-1 text-xs text-gray-300 leading-tight {{ $skill['trained'] ? 'font-semibold text-white' : '' }}">
                        {{ $skill['name'] }}
                        <span class="text-gray-600 text-[10px]">({{ $skill['attribute'] }})</span>
                    </span>

                    {{-- Valor --}}
                    <input type="number"
                        wire:model="skills.{{ $i }}.value"
                        class="w-10 text-center bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-white text-xs font-bold focus:border-amber-500 focus:ring-0 focus:outline-none">
                </div>
                @endforeach
            </div>
        </div>

        {{-- Espaço no final para respiro ao rolar --}}
        <div class="h-4"></div>
    </aside>

    {{-- ===== COLUNA DIREITA (área principal, a ser definida) ===== --}}
    <main class="flex-1 overflow-y-auto flex flex-col">

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
                    x-show="dirty && !savedMsg"
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
                    <span>✓</span> Salvo
                </span>
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

        {{-- Conteúdo direito (a ser construído) --}}
        <div class="flex-1 flex items-center justify-center text-gray-700 text-sm">
            Área principal — em construção
        </div>

    </main>

</div>
