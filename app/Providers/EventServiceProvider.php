<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

// Importa tu evento y listeners
use App\Events\FolioGenerado;
use App\Listeners\EnviarFolioPorCorreo;
use App\Listeners\EnviarNotificacionFcm;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        FolioGenerado::class => [
            EnviarFolioPorCorreo::class,
            EnviarNotificacionFcm::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Si necesitas manualmente, puedes dejarlo vacío.
        // parent::boot(); // en Laravel moderno ya no es necesario llamarlo.
    }

    /**
     * Indica si se deben descubrir eventos automáticamente.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // déjalo en false porque ya definimos $listen
    }
}
