<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            Convite para campanha
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <p class="text-sm text-gray-400">Você foi convidado para a campanha</p>
                    <h3 class="text-2xl font-bold text-gray-100 mt-1">{{ $campaign->name }}</h3>
                    <p class="text-sm text-gray-400 mt-1">Mestre: {{ $campaign->owner->name }}</p>
                    @if($campaign->description)
                        <p class="text-gray-300 mt-3 whitespace-pre-line">{{ $campaign->description }}</p>
                    @endif
                </div>
            </div>

            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-2">Escolha quais fichas adicionar</h3>
                    <p class="text-sm text-gray-400 mb-4">Você pode adicionar uma ou mais fichas. Também pode entrar sem ficha e adicionar depois.</p>

                    @if($myCharacters->isEmpty())
                        <p class="text-gray-400 text-sm mb-4">Você ainda não tem fichas.</p>
                        <form method="POST" action="{{ route('fichas.criar') }}">
                            @csrf
                            <button type="submit"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition-colors">
                                + Criar uma ficha primeiro
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('convite.entrar', $campaign->invite_token) }}" class="space-y-3">
                        @csrf
                        @foreach($myCharacters as $character)
                            @php $isIn = in_array($character->id, $alreadyIn); @endphp
                            <label class="flex items-center gap-3 border border-gray-700 rounded-lg p-3 {{ $isIn ? 'opacity-60' : 'hover:border-amber-400 cursor-pointer' }}">
                                <input type="checkbox" name="characters[]" value="{{ $character->id }}"
                                    {{ $isIn ? 'checked disabled' : '' }}
                                    class="rounded bg-gray-900 border-gray-600 text-amber-600 focus:ring-amber-500">
                                <span class="text-sm font-medium text-gray-200">{{ $character->name ?? 'Sem nome' }}</span>
                                <span class="text-xs text-gray-400">Nível {{ $character->level }}</span>
                                @if($isIn)
                                    <span class="text-xs text-green-400 ml-auto">já na campanha</span>
                                @endif
                            </label>
                        @endforeach

                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition-colors">
                                Entrar na campanha
                            </button>
                            <a href="{{ route('campanhas.index') }}" class="text-sm text-gray-400 hover:text-amber-300">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
