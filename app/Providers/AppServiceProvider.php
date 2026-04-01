<?php

namespace App\Providers;

use App\Guards\CopropietarioGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        Auth::extend('copropietario', function ($app, $name, array $config) {
            return new CopropietarioGuard($app['request']);
        });
    }
}
