<?php

namespace App\Providers;

use App\Services\Email\EmailProviderManager;
use Illuminate\Support\ServiceProvider;

class EmailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(EmailProviderManager::class, function ($app) {
            return new EmailProviderManager($app);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
