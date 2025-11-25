<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Escuela;
use App\Observers\EscuelaObserver;

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
        // Registrar observer de Escuela para geocodificación automática
        Escuela::observe(EscuelaObserver::class);
    }
}
