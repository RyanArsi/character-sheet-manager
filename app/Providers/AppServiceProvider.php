<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Atrás do proxy do ddev a requisição interna chega como HTTP, fazendo os assets
        // (@vite, livewire.js) serem gerados em http e bloqueados por Mixed Content numa
        // página HTTPS. Força o esquema quando a app é servida sob HTTPS.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
