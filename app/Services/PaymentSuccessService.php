<?php

namespace App\Services;

use App\Events\FolioGenerado;
use App\Mail\FolioGeneradoMail;
use App\Mail\PaymentReceiptMail;
use App\Models\Documento;
use App\Models\DocumentoRevision;
use App\Models\Pago;
use App\Support\FolioGenerator;
use Illuminate\Support\Facades\Mail;

class PaymentSuccessService
{
    private const SEGURO_DOC_NAME = 'Pago de Seguro y Credencial';

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
        $shouldSendReceipt = $validEmail && !$pago->receipt_sent_at;

        if ($shouldSendReceipt) {
            Mail::to($validEmail)->send(new PaymentReceiptMail($pago));
            $pago->forceFill(['receipt_sent_at' => now()])->save();
        }

        if ($validEmail && $folioGenerated && !empty($aspirante->folio_examen)) {
            Mail::to($validEmail)->send(new FolioGeneradoMail($aspirante, $aspirante->folio_examen));
        }

        if ($folioGenerated) {
            FolioGenerado::dispatch($aspirante->fresh(), $aspirante->folio_examen);
        }

        $this->syncSeguroDocument($pago);
    }

    private function syncSeguroDocument(Pago $pago): void
    {
        $seguroConfigId = (int) config('admissions.seguro_payment_config_id', 4);

        if ((int) $pago->id_configuracion !== $seguroConfigId) {
            return;
        }

        $aspiranteId = $pago->id_aspirantes;
        if (!$aspiranteId) {
            return;
        }

        $documento = Documento::firstOrCreate(
            [
                'id_aspirantes' => $aspiranteId,
                'nombre' => self::SEGURO_DOC_NAME,
            ],
            [
                'fecha_registro' => now(),
                'estado_validacion' => 0,
            ]
        );

        $documento->forceFill([
            'estado_validacion' => 2,
            'observaciones' => sprintf('Pago validado automáticamente por Stripe (%s).', $pago->referencia ?? 'sin referencia'),
            'fecha_validacion' => now(),
            'id_validador' => null,
        ])->save();

        DocumentoRevision::create([
            'id_documentos' => $documento->id_documentos,
            'id_validador' => null,
            'estado' => 2,
            'observaciones' => 'Validación automática por pago Stripe.',
            'fecha_evento' => now(),
        ]);
    }
}
