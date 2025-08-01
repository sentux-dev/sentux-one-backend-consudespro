<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
       RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(5, 5)
            ->by($request->ip())
            ->response(function () {
                return response()->json([
                    'message' => 'Demasiados intentos. Intenta de nuevo en 5 minutos.'
                ], 429);
            }); // ⏳ Espera de 5 minutos si se supera el límite
        });

        // Si ya tenés esto para API, lo dejás:
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
