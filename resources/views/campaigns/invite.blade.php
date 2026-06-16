<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Convite para campanha
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <p class="text-sm text-gray-500">Você foi convidado para a campanha</p>
                    <h3 class="text-2xl font-bold text-gray-800 mt-1">{{ $campaign->name }}</h3>
                    <p class="text-sm text-gray-500 mt-1">Mestre: {{ $campaign->owner->name }}</p>
                    @if($campaign->description)
                        <p class="text-gray-700 mt-3 whitespace-pre-line">{{ $campaign->description }}</p>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Escolha quais fichas adicionar</h3>
                    <p class="text-sm text-gray-500 mb-4">Você pode adicionar uma ou mais fichas. Também pode entrar sem ficha e adicionar depois.</p>

                    @if($myCharacters->isEmpty())
                        <p class="text-gray-500 text-sm mb-4">Você ainda não tem fichas.</p>
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
                            <label class="flex items-center gap-3 border border-gray-200 rounded-lg p-3 {{ $isIn ? 'opacity-60' : 'hover:border-amber-400 cursor-pointer' }}">
                                <input type="checkbox" name="characters[]" value="{{ $character->id }}"
                                    {{ $isIn ? 'checked disabled' : '' }}
                                    class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                <span class="text-sm font-medium text-gray-800">{{ $character->name ?? 'Sem nome' }}</span>
                                <span class="text-xs text-gray-500">Nível {{ $character->level }}</span>
                                @if($isIn)
                                    <span class="text-xs text-green-600 ml-auto">já na campanha</span>
                                @endif
                            </label>
                        @endforeach

                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition-colors">
                                Entrar na campanha
                            </button>
                            <a href="{{ route('campanhas.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
