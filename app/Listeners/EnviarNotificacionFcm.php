<?php

namespace App\Listeners;

use App\Events\FolioGenerado;
use Illuminate\Contracts\Queue\ShouldQueue;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

class EnviarNotificacionFcm implements ShouldQueue
{
    public function __construct(private Messaging $messaging) {}

    public function handle(FolioGenerado $event): void
    {
        $asp = $event->aspirante;

        // Recupera tokens FCM registrados para este aspirante/usuario admin segun tu flujo
        // Aquí asumo tokens por aspirante (puedes hacerlo por admin/rol si es para staff).
        $tokens = $asp->deviceTokens()->pluck('fcm_token')->all();
        if (empty($tokens)) return;

        $title = 'Folio de examen generado';
        $body  = "Tu folio es: {$event->folio}";

        $data = [
            'tipo'          => 'folio_generado',
            'folio'         => $event->folio,
            'id_aspirantes' => (string)$asp->id_aspirantes,
        ];

        // multicast enviar a varios tokens
        $message = CloudMessage::new()
            ->withNotification(['title' => $title, 'body' => $body])
            ->withData($data);

        $this->messaging->sendMulticast($message, $tokens);
    }
}
