<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripePaymentService $stripePayments)
    {
        $webhookSecret = config('services.stripe.webhook_secret');
        if (!$webhookSecret) {
            Log::warning('Stripe webhook recibido sin secret configurado');
            return response()->json(['error' => 'Stripe webhook no configurado'], 500);
        }

        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Firma de Stripe inválida', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Firma inválida'], 400);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Payload de Stripe inválido', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Payload inválido'], 400);
        }

        $handled = false;
        $sessionEvents = [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ];

        if (in_array($event->type, $sessionEvents, true)) {
            $stripePayments->finalizeFromStripeSession($event->data->object);
            $handled = true;
        } elseif ($event->type === 'checkout.session.async_payment_failed') {
            Log::warning('Stripe reportó pago asíncrono fallido', [
                'session_id' => $event->data->object->id ?? null,
            ]);
            $handled = true;
        }

        return response()->json([
            'received' => true,
            'handled' => $handled,
            'type' => $event->type,
        ]);
    }
}
