<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // Não esqueça de importar a classe URL

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
       // Se o ambiente for produção (APP_ENV=production no .env), força o HTTPS
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
