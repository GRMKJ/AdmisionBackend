<?php

namespace App\Listeners;

use App\Events\FolioGenerado;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\FirebaseNotificationService;

class EnviarNotificacionFcm implements ShouldQueue
{
    public function __construct(private FirebaseNotificationService $notifications) {}

    public function handle(FolioGenerado $event): void
    {
        $this->notifications->notifyAspirante(
            $event->aspirante,
            'Folio de examen generado',
            "Tu folio es: {$event->folio}",
            [
                'tipo'      => 'folio_generado',
                'folio'     => $event->folio,
                'deeplink'  => 'siiadmision://folio/'.$event->folio,
            ],
        );
    }
}
