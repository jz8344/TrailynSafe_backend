<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Support\MongoSync\MongoSyncObserver;
use App\Models\Usuario;
use App\Models\Hijo;
use App\Models\Chofer;
use App\Models\Unidad;
use App\Models\Ruta;
use App\Models\Admin;
use App\Models\CodigoSeguridad;
use App\Models\Sesion;
use App\Models\SolicitudImpresionQr;
use App\Models\User;
use App\Models\SanctumPersonalAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

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
        $observer = $this->app->make(MongoSyncObserver::class);
        foreach ([Usuario::class, Hijo::class, Chofer::class, Unidad::class, Ruta::class, Admin::class, CodigoSeguridad::class, Sesion::class, SolicitudImpresionQr::class, User::class, SanctumPersonalAccessToken::class, PersonalAccessToken::class] as $modelClass) {
            $modelClass::observe($observer);
        }
    }
}
