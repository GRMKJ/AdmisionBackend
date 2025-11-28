<?php

namespace App\Listeners;

use App\Events\FolioGenerado;
use App\Mail\FolioGeneradoMail;
use Illuminate\Support\Facades\Mail;
use App\Services\FirebaseNotificationService;

class EnviarFolioPorCorreo
{
    public function __construct(private FirebaseNotificationService $notifications)
    {
    }

    public function handle(FolioGenerado $event): void
    {
        $asp = $event->aspirante;
        if (!filter_var($asp->email, FILTER_VALIDATE_EMAIL)) return;

        Mail::to($asp->email)->send(new FolioGeneradoMail($asp, $event->folio));

        $this->notifications->notifyAspirante(
            $asp,
            'Folio de examen generado',
            "Tu folio es: {$event->folio}",
            [
                'tipo' => 'folio_generado',
                'folio' => $event->folio,
                'deeplink' => 'siiadmision://folio/'.$event->folio,
            ],
        );
    }
}
