<?php

namespace App\Services;

use App\Events\FolioGenerado;
use App\Mail\FolioGeneradoMail;
use App\Mail\PaymentReceiptMail;
use App\Models\Pago;
use App\Support\FolioGenerator;
use Illuminate\Support\Facades\Mail;

class PaymentSuccessService
{
    public function handle(Pago $pago): void
    {
        if ((int) $pago->estado_validacion !== Pago::EST_VALIDADO) {
            return;
        }

        $pago->loadMissing(['aspirante', 'configuracion']);
        $aspirante = $pago->aspirante;
        if (!$aspirante) {
            return;
        }

        $folioGenerated = false;

        if (($aspirante->progress_step ?? 1) < 4) {
            $aspirante->progress_step = 4;
        }

        if (empty($aspirante->folio_examen)) {
            $aspirante->folio_examen = FolioGenerator::generate();
            $folioGenerated = true;
        }

        if ($aspirante->isDirty()) {
            $aspirante->save();
        }

        $validEmail = filter_var($aspirante->email, FILTER_VALIDATE_EMAIL) ? $aspirante->email : null;

        if ($validEmail) {
            Mail::to($validEmail)->send(new PaymentReceiptMail($pago));

            if ($folioGenerated && !empty($aspirante->folio_examen)) {
                Mail::to($validEmail)->send(new FolioGeneradoMail($aspirante, $aspirante->folio_examen));
            }
        }

        if ($folioGenerated) {
            FolioGenerado::dispatch($aspirante->fresh(), $aspirante->folio_examen);
        }
    }
}
