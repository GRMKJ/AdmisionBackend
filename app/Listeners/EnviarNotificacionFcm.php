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

    $tokens = $asp->deviceTokens()->pluck('fcm_token')->all();
    if (empty($tokens)) return;

    $title = 'Folio de examen generado';
    $body  = "Tu folio es: {$event->folio}";

    $data = [
        'tipo'          => 'folio_generado',
        'folio'         => $event->folio,
        'id_aspirantes' => (string)$asp->id_aspirantes,
    ];

    // 👇 Aquí defines el $message
    $message = CloudMessage::new()
        ->withNotification(['title' => $title, 'body' => $body])
        ->withData(array_merge($data, [
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'deeplink'     => 'siiadmision://folio/'.$event->folio,
        ]));

    // 👇 Aquí envías y limpias tokens inválidos
    $report = $this->messaging->sendMulticast($message, $tokens);
    foreach ($report->failures()->getItems() as $failure) {
        $invalidToken = $failure->target()->value();
        \App\Models\DeviceToken::where('fcm_token', $invalidToken)->delete();
    }
}
}
