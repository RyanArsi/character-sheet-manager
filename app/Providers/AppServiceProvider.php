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
        // Em ambiente local sob HTTPS (ddev), gera assets em https. Para o usuário real
        // o TrustProxies (bootstrap/app.php) já faz o Request reportar https, mantendo a
        // geração e a validação de URLs assinadas consistentes; este forceScheme cobre
        // também o navegador dos testes Dusk, que acessa o web container sem passar pelo proxy.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
