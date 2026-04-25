<?php

namespace App\Providers;

use App\Guards\CopropietarioGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

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
        Vite::prefetch(concurrency: 3);

        Auth::extend('copropietario', function ($app, $name, array $config) {
            return new CopropietarioGuard($app['request']);
        });

        // Rate limiter para login de copropietarios: 5 intentos por minuto por IP + reunión
        RateLimiter::for('sala-login', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->ip() . '|' . $request->route('reunion')
            );
        });

        // Cargar canales de broadcasting sin registrar la ruta default de Broadcast::routes().
        // La ruta /broadcasting/auth está definida manualmente en routes/web.php para soportar
        // tanto el guard web (admin) como el guard copropietario (PIN-based).
        require base_path('routes/channels.php');
    }
}
