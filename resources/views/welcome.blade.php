<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Naruto RPG') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-900 text-gray-200">
        <div class="min-h-screen flex flex-col">
            {{-- Topo --}}
            @if (Route::has('login'))
                <nav class="flex items-center justify-end gap-3 px-6 py-4">
                    @auth
                        <a href="{{ url('/dashboard') }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg text-gray-300 hover:text-amber-300 transition-colors">
                            Entrar
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                                Criar conta
                            </a>
                        @endif
                    @endauth
                </nav>
            @endif

            {{-- Centro --}}
            <main class="flex-1 flex flex-col items-center justify-center text-center px-6 -mt-12">
                <x-application-logo class="w-20 h-20 fill-current text-amber-500" />
                <h1 class="mt-6 text-4xl font-black tracking-tight text-amber-400">{{ config('app.name', 'Naruto RPG') }}</h1>
                <p class="mt-3 max-w-md text-gray-400">
                    Crie e gerencie fichas de personagem, role dados e mestre suas campanhas em tempo real.
                </p>

                @guest
                    <div class="mt-8 flex items-center gap-3">
                        <a href="{{ route('register') }}"
                            class="px-5 py-2.5 text-sm font-semibold rounded-lg bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                            Começar agora
                        </a>
                        <a href="{{ route('login') }}"
                            class="px-5 py-2.5 text-sm font-semibold rounded-lg border border-gray-700 text-gray-200 hover:border-amber-400 hover:text-amber-300 transition-colors">
                            Já tenho conta
                        </a>
                    </div>
                @else
                    <a href="{{ url('/dashboard') }}"
                        class="mt-8 px-5 py-2.5 text-sm font-semibold rounded-lg bg-amber-600 hover:bg-amber-500 text-white transition-colors">
                        Ir para o painel
                    </a>
                @endguest
            </main>
        </div>
    </body>
</html>
