<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// (Opcional) tus middlewares de tu app
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\PreventRequestsDuringMaintenance;

// Sanctum (abilities)
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
// (Opcional, solo si usaras cookies/stateful)
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        // Si usas Sanctum y no lo auto-descubre, agrega también:
        // Laravel\Sanctum\SanctumServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        /**
         * Aliases de middlewares (reemplazan lo que antes iba en Kernel::$routeMiddleware)
         */
        $middleware->redirectGuestsTo(function ($request) {
            return $request->is('api/*') ? null : '/login'; // o null si no tienes login web
        });
        $middleware->alias([
            // Auth básico de tu app (pon los que uses)
            //'auth'       => Authenticate::class,
            //'trim'       => TrimStrings::class,
            //'maintenance'=> PreventRequestsDuringMaintenance::class,

            // Sanctum abilities (¡estos son los importantes!)
            'ability'    => CheckForAnyAbility::class, // OR: al menos una ability
            'abilities'  => CheckAbilities::class,     // AND: todas las abilities
        ]);

        /**
         * (Opcional) Ajustes al grupo 'api'
         * En APIs con tokens personales (Sanctum), NO necesitas EnsureFrontendRequestsAreStateful.
         * Si quisieras rate-limit o extra middlewares, puedes agregarlos:
         */
        $middleware->appendToGroup('api', [
            // \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            // Más middlewares si los quieres globales al grupo api
        ]);

        /**
         * (Opcional) Si tuvieras sesión/cookies SPA y quisieras Sanctum stateful:
         */
        // $middleware->prependToGroup('web', [
        //     EnsureFrontendRequestsAreStateful::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
