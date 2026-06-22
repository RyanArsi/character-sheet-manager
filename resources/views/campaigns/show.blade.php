<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ $campaign->name }}
            </h2>
            <a href="{{ route('campanhas.index') }}" class="text-sm text-gray-400 hover:text-amber-300">&larr; Campanhas</a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ tab: 'campanha' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Abas --}}
            <div class="flex items-end gap-1 mb-6 border-b border-gray-700">
                @php $campTabs = ['campanha' => 'Campanha', 'biblioteca' => 'Biblioteca'] + ($isOwner ? ['fichas' => 'Fichas', 'combate' => 'Combate'] : []); @endphp
                @foreach($campTabs as $key => $label)
                    <button type="button" @click="tab = '{{ $key }}'"
                        dusk="camp-tab-{{ $key }}"
                        :class="tab === '{{ $key }}'
                            ? 'bg-gray-800 text-amber-400 border-gray-700 border-b-gray-800'
                            : 'bg-transparent text-gray-400 hover:text-gray-200 border-transparent'"
                        class="px-4 py-2 text-sm font-medium rounded-t-lg border border-b-0 -mb-px transition-colors">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- ===== Aba Campanha ===== --}}
            <div x-show="tab === 'campanha'" class="space-y-6">

            @if (session('status'))
                <div class="bg-green-900/30 border border-green-700 text-green-300 text-sm rounded-lg px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Cabeçalho --}}
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <p class="text-sm text-gray-400">Mestre: <span class="font-medium text-gray-200">{{ $campaign->owner->name }}</span></p>
                    @if($campaign->description)
                        <p class="text-gray-300 mt-2 whitespace-pre-line">{{ $campaign->description }}</p>
                    @endif
                </div>
            </div>

            {{-- Link de convite (só o mestre) --}}
            @if($isOwner)
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg" x-data="{ copied: false }">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-2">Link de Convite</h3>
                    <p class="text-sm text-gray-400 mb-3">Compartilhe este link para outros jogadores entrarem e adicionarem suas fichas.</p>
                    @php $inviteUrl = route('convite.ver', $campaign->invite_token); @endphp
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="text" readonly value="{{ $inviteUrl }}"
                            x-ref="invite"
                            class="flex-1 bg-gray-900 border-gray-700 text-gray-300 text-sm rounded-md shadow-sm" />
                        <button type="button"
                            @click="
                                $refs.invite.select();
                                $refs.invite.setSelectionRange(0, 99999);
                                try {
                                    if (navigator.clipboard && window.isSecureContext) {
                                        await navigator.clipboard.writeText($refs.invite.value);
                                    } else {
                                        document.execCommand('copy');
                                    }
                                    copied = true;
                                    setTimeout(() => copied = false, 2000);
                                } catch (e) {
                                    document.execCommand('copy');
                                    copied = true;
                                    setTimeout(() => copied = false, 2000);
                                }
                            "
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                            <span x-show="!copied">Copiar link</span>
                            <span x-show="copied">Copiado!</span>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('campanhas.regenerar-convite', $campaign) }}" class="mt-3"
                        onsubmit="return confirm('Gerar um novo link? O link antigo deixará de funcionar.')">
                        @csrf
                        <button type="submit" class="text-xs text-gray-400 hover:text-red-400 underline">
                            Gerar novo link (invalida o atual)
                        </button>
                    </form>
                </div>
            </div>
            @endif

            {{-- Adicionar minhas fichas --}}
            @php
                $myCharacters = auth()->user()->characters()->whereNull('campaign_id')->get();
                $inCampaignIds = $campaign->characters->pluck('id');
                $available = $myCharacters->whereNotIn('id', $inCampaignIds);
            @endphp
            @if($available->isNotEmpty())
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-3">Adicionar minhas fichas</h3>
                    <form method="POST" action="{{ route('convite.entrar', $campaign->invite_token) }}" class="space-y-3">
                        @csrf
                        @foreach($available as $character)
                            <label class="flex items-center gap-3 border border-gray-700 rounded-lg p-3 hover:border-amber-400 cursor-pointer">
                                <input type="checkbox" name="characters[]" value="{{ $character->id }}"
                                    class="rounded bg-gray-900 border-gray-600 text-amber-600 focus:ring-amber-500">
                                <span class="text-sm font-medium text-gray-200">{{ $character->name ?? 'Sem nome' }}</span>
                                <span class="text-xs text-gray-400">Nível {{ $character->level }}</span>
                            </label>
                        @endforeach
                        <button type="submit"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition-colors">
                            Adicionar selecionadas
                        </button>
                    </form>
                </div>
            </div>
            @endif

            {{-- Fichas na campanha --}}
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Fichas na Campanha</h3>

                    @if($campaign->characters->isEmpty())
                        <p class="text-gray-400 text-sm">Nenhuma ficha adicionada ainda.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($campaign->characters as $character)
                            <div class="bg-gray-900 border border-gray-700 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center overflow-hidden flex-shrink-0">
                                        @if($character->avatar)
                                            <img src="{{ Storage::url($character->avatar) }}" alt="" class="w-full h-full object-cover">
                                        @else
                                            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-100 truncate">{{ $character->name ?? 'Sem nome' }}</p>
                                        <p class="text-xs text-gray-400">Jogador: {{ $character->user->name }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    @if($isOwner || $character->user_id === auth()->id())
                                        <a href="{{ route('fichas.editar', $character) }}" class="text-xs text-amber-400 hover:underline">
                                            {{ $character->user_id === auth()->id() ? 'Editar ficha' : 'Ver / editar ficha' }}
                                        </a>
                                    @else
                                        <span></span>
                                    @endif
                                    @if($isOwner || $character->user_id === auth()->id())
                                        <form method="POST" action="{{ route('campanhas.remover-ficha', [$campaign, $character]) }}"
                                            onsubmit="return confirm('Remover esta ficha da campanha?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-gray-400 hover:text-red-400">Remover</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Feed de eventos ao vivo --}}
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg"
                x-data="campaignFeed({{ $campaign->id }}, @js($events))" x-init="init()">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-100">Eventos ao vivo</h3>
                        <span class="flex items-center gap-1.5 text-xs text-gray-400">
                            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> tempo real
                        </span>
                    </div>

                    <template x-if="!feed.length">
                        <p class="text-gray-400 text-sm">Nenhum evento ainda. Rolagens de dados feitas nas fichas aparecerão aqui.</p>
                    </template>

                    <ul class="space-y-2 max-h-96 overflow-y-auto">
                        <template x-for="(ev, i) in feed" :key="ev.id ?? i">
                            <li class="flex items-baseline justify-between gap-3 border border-gray-700 rounded-lg px-3 py-2 bg-gray-900">
                                <div class="min-w-0">
                                    <span class="text-sm font-semibold text-amber-400" x-text="ev.actor"></span>
                                    <span class="text-sm text-gray-200" x-html="' ' + fmtCampaignEvent(ev.message, ev.detail, true)"></span>
                                </div>
                                <span class="text-xs text-gray-500 flex-shrink-0" x-text="ev.time"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            {{-- Membros --}}
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Membros</h3>
                    <ul class="divide-y divide-gray-700">
                        @foreach($campaign->members as $member)
                            <li class="py-2 flex items-center justify-between gap-3">
                                <span class="text-sm text-gray-200">{{ $member->name }}</span>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-medium {{ $member->pivot->role === 'owner' ? 'text-amber-300 bg-amber-500/15' : 'text-gray-300 bg-gray-700' }} rounded-full px-2 py-0.5">
                                        {{ $member->pivot->role === 'owner' ? 'Mestre' : 'Jogador' }}
                                    </span>
                                    @if($isOwner && $member->id !== $campaign->owner_id)
                                        <form method="POST" action="{{ route('campanhas.banir-membro', [$campaign, $member]) }}"
                                            onsubmit="return confirm('Banir {{ $member->name }}? As fichas dele serão removidas da campanha.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-gray-400 hover:text-red-400">Banir</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Excluir (só o mestre) --}}
            @if($isOwner)
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('campanhas.excluir', $campaign) }}"
                        onsubmit="return confirm('Excluir a campanha? Isso não apaga as fichas, só a campanha.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-500 hover:text-red-400">Excluir campanha</button>
                    </form>
                </div>
            </div>
            @endif

            </div> {{-- /Aba Campanha --}}

            {{-- ===== Aba Biblioteca ===== --}}
            <div x-show="tab === 'biblioteca'" x-cloak>
                <livewire:campaign-library :campaign-id="$campaign->id" :key="'campaign-library-'.$campaign->id" />
            </div>

            {{-- ===== Aba Fichas (só o mestre) ===== --}}
            @if($isOwner)
                <div x-show="tab === 'fichas'" x-cloak>
                    <livewire:campaign-sheets :campaign-id="$campaign->id" :key="'campaign-sheets-'.$campaign->id" />
                </div>

                {{-- ===== Aba Combate (só o mestre) ===== --}}
                <div x-show="tab === 'combate'" x-cloak>
                    <livewire:combat-panel :campaign-id="$campaign->id" :key="'combat-panel-'.$campaign->id" />
                </div>
            @endif

        </div>
    </div>

    @include('partials.campaign-event-format')
    @if($isOwner)
        @include('partials.dice-roller')
        @include('partials.combat-panel-js')
    @endif
    <script>
        function campaignFeed(campaignId, initial) {
            return {
                feed: initial || [],
                init() {
                    if (!window.Echo) return;
                    window.Echo.private('campaign.' + campaignId)
                        .listen('.CampaignEventBroadcast', (e) => {
                            this.feed.unshift(e);
                            if (this.feed.length > 100) this.feed.pop();
                        });
                },
            };
        }
    </script>
</x-app-layout>
