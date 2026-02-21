<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // <-- 1. Es crucial agregar esta importación arriba

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
        // 2. Forzamos HTTPS si la aplicación no está en modo local
        if (env('APP_ENV') !== 'local') {
            URL::forceScheme('https');
        }
    }
}