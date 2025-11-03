<?php

namespace App\Listeners;

use App\Events\FolioGenerado;
use App\Mail\FolioGeneradoMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class EnviarFolioPorCorreo
{
    public function handle(FolioGenerado $event): void
    {
        $asp = $event->aspirante;
        if (!filter_var($asp->email, FILTER_VALIDATE_EMAIL)) return;

        Mail::to($asp->email)->send(new FolioGeneradoMail($asp, $event->folio));
    }
}
