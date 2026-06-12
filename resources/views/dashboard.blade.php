<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Fichas --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Minhas Fichas</h3>
                        <form method="POST" action="{{ route('fichas.criar') }}">
                            @csrf
                            <button type="submit"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition-colors">
                                + Nova Ficha
                            </button>
                        </form>
                    </div>

                    @php
                        $characters = auth()->user()->characters ?? collect();
                    @endphp

                    @if($characters->isEmpty())
                        <p class="text-gray-500 text-sm">Você ainda não tem fichas. Crie uma para começar!</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($characters as $character)
                            <a href="{{ route('fichas.editar', $character) }}"
                                class="block border border-gray-200 rounded-lg p-4 hover:border-amber-400 hover:shadow-md transition-all group">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center overflow-hidden flex-shrink-0">
                                        @if($character->avatar)
                                            <img src="{{ Storage::url($character->avatar) }}" alt="" class="w-full h-full object-cover">
                                        @else
                                            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-800 group-hover:text-amber-700 truncate">{{ $character->name }}</p>
                                        <p class="text-xs text-gray-500">
                                            Nível {{ $character->level }}
                                            @if($character->village) · {{ $character->village }} @endif
                                        </p>
                                    </div>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
