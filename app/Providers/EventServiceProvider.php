<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// ✅ 1. Importar el Evento y el Listener que creamos
use App\Events\Sales\QuoteAccepted;
use App\Listeners\Sales\DeductStockFromQuoteListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        
        // ✅ 2. Registrar nuestra nueva regla de negocio
        // Cuando el evento QuoteAccepted se dispare...
        QuoteAccepted::class => [
            // ...ejecuta el listener DeductStockFromQuoteListener.
            DeductStockFromQuoteListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}