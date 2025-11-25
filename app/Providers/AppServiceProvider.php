<?php

namespace App\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('files', fn () => new Filesystem);

        $this->app->singleton(StripeClient::class, function () {
            $secret = config('services.stripe.secret');
            if (!$secret) {
                throw new \RuntimeException('Stripe secret key no está configurada.');
            }

            return new StripeClient($secret);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
