<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate; 
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Crm\Task;
use App\Models\Integration;
use App\Policies\ContactPolicy;
use App\Policies\DealPolicy;
use App\Policies\IntegrationPolicy;
use App\Policies\TaskPolicy;

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
        Gate::policy(Integration::class, IntegrationPolicy::class); 
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);

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
            if ($request->user()) {
                // Si el usuario está autenticado, le damos un límite más alto.
                return Limit::perMinute(200)->by($request->user()->id);
            }
            
            // Si no está autenticado (es un invitado), le damos el límite estándar.
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
