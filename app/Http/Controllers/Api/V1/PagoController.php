<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\PagoStoreRequest;
use App\Http\Requests\V1\PagoUpdateRequest;
use App\Http\Resources\V1\PagoResource;
use App\Models\Pago;
use App\Models\Aspirante;
use App\Models\ConfiguracionPago;
use App\Services\StripePaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stripe\StripeClient;

class PagoController extends Controller
{
    use ApiResponse;

    private const STRIPE_FEE_RATE = 0.036;

    public function index(Request $request)
    {
        $q = Pago::query()->with(['aspirante', 'configuracion']);

        if ($request->filled('id_aspirantes'))
            $q->where('id_aspirantes', $request->integer('id_aspirantes'));
        if ($request->filled('id_configuracion'))
            $q->where('id_configuracion', $request->integer('id_configuracion'));
        if ($tp = $request->get('tipo'))
            $q->where('tipo_pago', $tp);
        if ($met = $request->get('metodo'))
            $q->where('metodo_pago', $met);
        if ($desde = $request->get('desde'))
            $q->whereDate('fecha_pago', '>=', $desde);
        if ($hasta = $request->get('hasta'))
            $q->whereDate('fecha_pago', '<=', $hasta);

        $rows = $q->latest('id_pagos')->paginate((int) $request->get('per_page', 15));

        return $this->ok([
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
            'data' => PagoResource::collection($rows->items()),
        ]);
    }

    public function store(PagoStoreRequest $request)
    {
        $data = $request->validated();

        if (!array_key_exists('monto_pagado', $data) || $data['monto_pagado'] === null) {
            $config = !empty($data['id_configuracion']) ? ConfiguracionPago::find($data['id_configuracion']) : null;
            if ($config) {
                $data['monto_pagado'] = $config->monto;
            }
        }

        // Manejar comprobante si viene
        if ($request->hasFile('comprobante')) {
            $dir = 'comprobantes/' . ($data['id_aspirantes']);
            $path = $request->file('comprobante')->storeAs(
                $dir,
                $this->buildFileName($request->file('comprobante')->getClientOriginalName()),
                'public'
            );
            $data['comprobante_pago'] = $path;
        }

        $row = Pago::create($data);
        $row->load(['aspirante', 'configuracion']);
        $this->advanceAspiranteStep($row);

        return $this->ok(new PagoResource($row), 'Creado', 201);
    }

    public function show(Pago $pago)
    {
        $pago->load(['aspirante', 'configuracion']);
        return $this->ok(new PagoResource($pago));
    }

    public function update(PagoUpdateRequest $request, Pago $pago)
    {
        $data = $request->validated();

        // Borrar comprobante sin subir otro
        if (!empty($data['delete_comprobante']) && $pago->comprobante_pago) {
            Storage::disk('public')->delete($pago->comprobante_pago);
            $data['comprobante_pago'] = null;
        }

        // Reemplazar comprobante si viene
        if ($request->hasFile('comprobante')) {
            if ($pago->comprobante_pago) {
                Storage::disk('public')->delete($pago->comprobante_pago);
            }
            $dir = 'comprobantes/' . ($data['id_aspirantes'] ?? $pago->id_aspirantes);
            $path = $request->file('comprobante')->storeAs(
                $dir,
                $this->buildFileName($request->file('comprobante')->getClientOriginalName()),
                'public'
            );
            $data['comprobante_pago'] = $path;
        }

        $pago->update($data);
        $pago->load(['aspirante', 'configuracion']);

        return $this->ok(new PagoResource($pago), 'Actualizado');
    }

    public function destroy(Pago $pago)
    {
        if ($pago->comprobante_pago) {
            Storage::disk('public')->delete($pago->comprobante_pago);
        }
        $pago->delete();
        return $this->ok(null, 'Eliminado', 204);
    }

    /**
     * POST /v1/pagos/{pago}/comprobante
     * Subir o reemplazar SOLO el comprobante
     */
    public function uploadComprobante(Request $request, Pago $pago)
    {
        $request->validate([
            'comprobante' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        if ($pago->comprobante_pago) {
            Storage::disk('public')->delete($pago->comprobante_pago);
        }

        $dir = 'comprobantes/' . $pago->id_aspirantes;
        $path = $request->file('comprobante')->storeAs(
            $dir,
            $this->buildFileName($request->file('comprobante')->getClientOriginalName()),
            'public'
        );

        $pago->update(['comprobante_pago' => $path]);
        $pago->refresh();

        return $this->ok(new PagoResource($pago), 'Comprobante subido');
    }

    /**
     * DELETE /v1/pagos/{pago}/comprobante
     * Borrar SOLO el comprobante (mantener registro del pago)
     */
    public function deleteComprobante(Pago $pago)
    {
        if (!$pago->comprobante_pago) {
            return $this->error('No hay comprobante para borrar', 422);
        }
        Storage::disk('public')->delete($pago->comprobante_pago);
        $pago->update(['comprobante_pago' => null]);

        return $this->ok(new PagoResource($pago), 'Comprobante borrado');
    }

    public function createStripeSession(Request $request, StripeClient $stripeClient)
    {
        $aspirante = $request->user();
        if (!$aspirante instanceof Aspirante) {
            return $this->error('Solo los aspirantes pueden iniciar un pago en línea.', 403);
        }

        $data = $request->validate([
            'bachillerato_id' => ['required', 'exists:bachilleratos,id_bachillerato'],
            'promedio' => ['required', 'numeric', 'min:0', 'max:10'],
            'carrera_id' => ['required', 'exists:carreras,id_carreras'],
        ]);

        $config = ConfiguracionPago::orderByDesc('id_configuracion')->first();
        if (!$config) {
            return $this->error('No existe configuración de pago activa.', 422);
        }

        $this->applyAcademicSelection($aspirante, $data);

        $baseAmount = (float) $config->monto;
        $totalAmount = round($baseAmount * (1 + self::STRIPE_FEE_RATE), 2);
        $feeAmount = round($totalAmount - $baseAmount, 2);
        $currency = strtolower(config('services.stripe.currency', 'mxn'));

        $successUrl = $this->urlWithSessionPlaceholder(config('services.stripe.success_url'));
        $cancelUrl = $this->urlWithSessionPlaceholder(config('services.stripe.cancel_url'));

        $metadata = [
            'aspirante_id' => (string) $aspirante->id_aspirantes,
            'configuracion_id' => (string) $config->id_configuracion,
            'carrera_id' => (string) $data['carrera_id'],
            'bachillerato_id' => (string) $data['bachillerato_id'],
            'promedio' => (string) $data['promedio'],
        ];

        $payload = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => (int) round($totalAmount * 100),
                    'product_data' => [
                        'name' => $config->concepto ?? 'Pago de examen de admisión',
                    ],
                ],
            ]],
        ];

        if (!empty($aspirante->email)) {
            $payload['customer_email'] = $aspirante->email;
        }

        try {
            $session = $stripeClient->checkout->sessions->create($payload);
        } catch (\Throwable $e) {
            Log::error('Error creando sesión de Stripe', ['message' => $e->getMessage()]);
            return $this->error('No se pudo iniciar el pago con Stripe. Intenta nuevamente.', 502);
        }

        Pago::updateOrCreate(
            ['stripe_session_id' => $session->id],
            [
                'id_aspirantes' => $aspirante->id_aspirantes,
                'id_configuracion' => $config->id_configuracion,
                'tipo_pago' => 'admisión',
                'metodo_pago' => 'stripe',
                'estado_validacion' => Pago::EST_PENDIENTE,
                'referencia' => $session->id,
            ]
        );

        return $this->ok([
            'session_id' => $session->id,
            'checkout_url' => $session->url,
            'amount_base' => $baseAmount,
            'amount_fee' => $feeAmount,
            'amount_total' => $totalAmount,
            'currency' => strtoupper($currency),
        ], 'Sesión de pago creada');
    }

    public function showStripeSession(
        string $session,
        Request $request,
        StripePaymentService $stripePayments
    ) {
        $aspirante = $request->user();
        if (!$aspirante instanceof Aspirante) {
            return $this->error('Solo los aspirantes pueden consultar su pago.', 403);
        }

        $pago = Pago::where('stripe_session_id', $session)
            ->where('id_aspirantes', $aspirante->id_aspirantes)
            ->first();

        if (!$pago || $pago->estado_validacion !== Pago::EST_VALIDADO) {
            try {
                $stripeSession = $stripePayments->retrieveSession($session);
                $meta = $this->stripeMetadataArray($stripeSession->metadata ?? []);
                $sessionAspiranteId = isset($meta['aspirante_id']) ? (int) $meta['aspirante_id'] : null;

                if ($sessionAspiranteId !== null && $sessionAspiranteId !== (int) $aspirante->id_aspirantes) {
                    return $this->error('Sesión no encontrada.', 404);
                }

                $pago = $stripePayments->finalizeFromStripeSession($stripeSession) ?? $pago;
            } catch (\Throwable $e) {
                Log::warning('Consulta de sesión Stripe pendiente', [
                    'session' => $session,
                    'error' => $e->getMessage(),
                ]);

                if (!$pago) {
                    return $this->ok([
                        'session_id' => $session,
                        'estado_validacion' => null,
                        'message' => 'Tu pago sigue en proceso, intenta verificar más tarde.',
                    ], 'Sesión en proceso');
                }
            }
        }

        if (!$pago || (int) $pago->id_aspirantes !== (int) $aspirante->id_aspirantes) {
            return $this->error('Sesión no encontrada.', 404);
        }

        return $this->ok($this->formatStripeStatus($pago));
    }

    private function buildFileName(string $original): string
    {
        $name = pathinfo($original, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        return Str::slug($name) . '-' . time() . '.' . $ext;
    }

    private function applyAcademicSelection(Aspirante $aspirante, array $data): void
    {
        $aspirante->id_bachillerato = $data['bachillerato_id'];
        $aspirante->id_carrera = $data['carrera_id'];
        $aspirante->promedio_general = $data['promedio'];
        if (($aspirante->progress_step ?? 1) < 3) {
            $aspirante->progress_step = 3;
        }
        $aspirante->save();
    }

    private function urlWithSessionPlaceholder(?string $url): string
    {
        $url = $url ?: config('app.url') . '/admision/pagoexamen';
        if (str_contains($url, '{CHECKOUT_SESSION_ID}')) {
            return $url;
        }
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'session_id={CHECKOUT_SESSION_ID}';
    }

    private function formatStripeStatus(Pago $pago): array
    {
        $pago->loadMissing(['aspirante', 'configuracion']);

        return [
            'session_id' => $pago->stripe_session_id,
            'estado_validacion' => $pago->estado_validacion,
            'referencia' => $pago->referencia,
            'monto_pagado' => $pago->monto_pagado ? (float) $pago->monto_pagado : null,
            'currency' => strtoupper(config('services.stripe.currency', 'mxn')),
            'updated_at' => optional($pago->updated_at)->toIso8601String(),
            'pago' => new PagoResource($pago),
        ];
    }

    private function advanceAspiranteStep(Pago $pago): void
    {
        $aspirante = $pago->aspirante;
        if (!$aspirante) {
            return;
        }

        if (($aspirante->progress_step ?? 1) < 4) {
            $aspirante->progress_step = 4;
            $aspirante->save();
        }
    }

    private function stripeMetadataArray($metadata): array
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

    public function storeAspirantePago(Request $request)
    {
        $request->validate([
            'bachillerato_id' => 'required|exists:bachilleratos,id_bachillerato',
            'promedio' => 'required|numeric|min:0|max:10',
            'carrera_id' => 'required|exists:carreras,id_carreras',
            'referencia' => 'required|string|max:255',
        ]);

        $aspirante = auth()->user();

        // Asociar Aspirante con el bachillerato existente
        $aspirante->id_bachillerato = $request->bachillerato_id;
        $aspirante->id_carrera = $request->carrera_id;
        $aspirante->promedio_general = $request->promedio;
        $aspirante->progress_step = 3; // Actualizar paso a 3 (pago)
        $aspirante->save();

        // Crear Pago
        $pago = Pago::create([
            'id_aspirantes' => $aspirante->id_aspirantes,
            'id_configuracion' => 1,
            'tipo_pago' => 'admisión',
            'metodo_pago' => 'deposito',
            'fecha_pago' => now(),
            'referencia' => $request->referencia,
        ]);

        return response()->json([
            'message' => 'Registro de pago exitoso',
            'aspirante' => $aspirante->load('bachillerato', 'carrera'),
            'pago' => $pago,
        ]);
    }


}
