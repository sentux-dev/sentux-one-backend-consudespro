<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate; 
use App\Models\Crm\Contact;      
use App\Policies\ContactPolicy;


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
        // 🔹 Registrar políticas de autorización
        Gate::policy(Contact::class, ContactPolicy::class);
        // 🔹 Configurar límites de tasa para la API
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
