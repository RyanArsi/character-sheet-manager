<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('Campanhas') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-900/30 border border-green-700 text-green-300 text-sm rounded-lg px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Nova campanha --}}
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Nova Campanha</h3>
                    <form method="POST" action="{{ route('campanhas.criar') }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="name" :value="'Nome'" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                :value="old('name')" required autofocus placeholder="Ex: A Vila Oculta da Folha" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="description" :value="'Descrição (opcional)'" />
                            <textarea id="description" name="description" rows="2"
                                class="mt-1 block w-full bg-gray-900 border-gray-700 text-gray-100 placeholder-gray-500 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm"
                                placeholder="Um resumo da sua campanha...">{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                        <button type="submit"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition-colors">
                            + Criar Campanha
                        </button>
                    </form>
                </div>
            </div>

            {{-- Lista --}}
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Minhas Campanhas</h3>

                    @if($campaigns->isEmpty())
                        <p class="text-gray-400 text-sm">Você ainda não participa de nenhuma campanha. Crie uma acima ou entre por um link de convite.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($campaigns as $campaign)
                            <a href="{{ route('campanhas.ver', $campaign) }}"
                                class="block bg-gray-900 border border-gray-700 rounded-lg p-4 hover:border-amber-400 hover:shadow-md transition-all group">
                                <div class="flex items-center justify-between">
                                    <p class="font-medium text-gray-100 group-hover:text-amber-400 truncate">{{ $campaign->name }}</p>
                                    @if($campaign->owner_id === auth()->id())
                                        <span class="text-xs font-medium text-amber-300 bg-amber-500/15 rounded-full px-2 py-0.5">Mestre</span>
                                    @else
                                        <span class="text-xs font-medium text-gray-300 bg-gray-700 rounded-full px-2 py-0.5">Jogador</span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-400 mt-1">
                                    Mestre: {{ $campaign->owner->name }} · {{ $campaign->characters_count }} {{ \Illuminate\Support\Str::plural('ficha', $campaign->characters_count) }}
                                </p>
                                @if($campaign->description)
                                    <p class="text-sm text-gray-400 mt-2 line-clamp-2">{{ $campaign->description }}</p>
                                @endif
                            </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
