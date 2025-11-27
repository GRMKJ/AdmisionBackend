<?php

namespace App\Services;

use App\Models\Aspirante;
use App\Models\Pago;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\StripeClient;

class StripePaymentService
{
    public function __construct(
        private StripeClient $stripeClient,
        private PaymentSuccessService $paymentSuccess,
    )
    {
    }

    public function retrieveSession(string $sessionId): StripeSession
    {
        return $this->stripeClient->checkout->sessions->retrieve($sessionId, [
            'expand' => ['payment_intent'],
        ]);
    }

    public function finalizeFromStripeSession(StripeSession $session): ?Pago
    {
        $sessionId = $session->id;
        if (!$sessionId) {
            return null;
        }

        $metadata = $this->metadataArray($session->metadata ?? []);
        $pago = Pago::where('stripe_session_id', $sessionId)->first();
        $aspiranteId = $metadata['aspirante_id'] ?? $pago?->id_aspirantes;

        if (!$aspiranteId) {
            Log::warning('Stripe session sin aspirante asociado', ['session_id' => $sessionId]);
            return $pago;
        }

        if (!$pago) {
            $pago = new Pago([
                'id_aspirantes' => $aspiranteId,
                'id_configuracion' => $metadata['configuracion_id'] ?? null,
                'tipo_pago' => 'admisión',
                'metodo_pago' => 'stripe',
                'estado_validacion' => Pago::EST_PENDIENTE,
            ]);
            $pago->stripe_session_id = $sessionId;
        }

        $wasValidated = (int) $pago->estado_validacion === Pago::EST_VALIDADO;

        $amount = $session->amount_total ? round($session->amount_total / 100, 2) : null;
        if ($amount !== null) {
            $pago->monto_pagado = $amount;
        }

        $paymentIntentId = $this->extractPaymentIntentId($session);
        if ($paymentIntentId) {
            $pago->stripe_payment_intent = $paymentIntentId;
        }

        $pago->referencia = $paymentIntentId ?? $pago->referencia ?? $sessionId;
        $pago->metodo_pago = 'stripe';

        if ($this->isSessionPaid($session)) {
            $pago->estado_validacion = Pago::EST_VALIDADO;
            $pago->fecha_pago = now();
        }

        $pago->save();

        $justValidated = !$wasValidated && (int) $pago->estado_validacion === Pago::EST_VALIDADO;

        $this->syncAspiranteData($pago->id_aspirantes, $metadata, $pago->estado_validacion);

        if ((int) $pago->estado_validacion === Pago::EST_VALIDADO) {
            $this->paymentSuccess->handle($pago, $justValidated);
        }

        return $pago->fresh(['aspirante', 'configuracion']);
    }

    private function extractPaymentIntentId(StripeSession $session): ?string
    {
        if (is_string($session->payment_intent)) {
            return $session->payment_intent;
        }

        if ($session->payment_intent && method_exists($session->payment_intent, '__get')) {
            return $session->payment_intent->id ?? null;
        }

        return null;
    }

    private function isSessionPaid(StripeSession $session): bool
    {
        return in_array($session->payment_status, ['paid', 'no_payment_required'], true)
            || in_array($session->status, ['complete'], true);
    }

    private function syncAspiranteData(int $aspiranteId, array $metadata, int $estadoValidacion): void
    {
        $aspirante = Aspirante::find($aspiranteId);
        if (!$aspirante) {
            return;
        }

        $updated = false;

        if (!empty($metadata['carrera_id']) && (int) $aspirante->id_carrera !== (int) $metadata['carrera_id']) {
            $aspirante->id_carrera = (int) $metadata['carrera_id'];
            $updated = true;
        }

        if (!empty($metadata['bachillerato_id']) && (int) $aspirante->id_bachillerato !== (int) $metadata['bachillerato_id']) {
            $aspirante->id_bachillerato = (int) $metadata['bachillerato_id'];
            $updated = true;
        }

        if (!empty($metadata['promedio'])) {
            $aspirante->promedio_general = $metadata['promedio'];
            $updated = true;
        }

        if ($updated) {
            $aspirante->save();
        }
    }

    private function metadataArray($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            return $metadata->toArray();
        }

        if (is_object($metadata)) {
            return (array) $metadata;
        }

        return [];
    }
}
