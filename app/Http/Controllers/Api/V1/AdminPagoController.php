<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Aspirante;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\FolioGenerado;
use App\Services\PaymentSuccessService;
use App\Support\FolioGenerator;

class AdminPagoController extends Controller
{
    private function ok($data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json(['ok' => true, 'message' => $message, 'data' => $data], $code);
    }

    private function error(string $message = 'Error', int $code = 400): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], $code);
    }

    private function findPagoByReferenciaOr404(string $referencia): Pago
    {
        $pago = Pago::with('aspirante')->where('referencia', $referencia)->first();
        abort_if(!$pago, 404, 'Pago no encontrado por referencia');
        return $pago;
    }

    public function show(Request $request, string $referencia): JsonResponse
    {
        $pago = Pago::with('aspirante.carrera', 'aspirante.bachillerato')
            ->where('referencia', $referencia)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'pago' => $pago,
            ]
        ]);
    }

    /** POST /admin/pago/{referencia}/validar */
    public function validar(Request $request, string $referencia, PaymentSuccessService $paymentSuccess): JsonResponse
    {
        $user = $request->user();
        if (!$user)
            return $this->error('No autenticado', 401);

        $pago = $this->findPagoByReferenciaOr404($referencia);

        // si ya está validado, idempotencia simple
        if ((int) $pago->estado_validacion === Pago::EST_VALIDADO) {
            return $this->ok(null, 'Pago ya estaba validado');
        }

        $pago->estado_validacion = Pago::EST_VALIDADO; // 1
        $pago->id_admin_validador = $user->id_administrativo;
        if (!$pago->fecha_pago) {
            $pago->fecha_pago = now();
        }
        $pago->save();
        $paymentSuccess->handle($pago, true);
        return $this->ok(null, 'Pago validado');
    }

    /** POST /admin/pago/{referencia}/invalidar  (opcional) */
    public function invalidar(Request $request, string $referencia): JsonResponse
    {
        $user = $request->user();
        if (!$user)
            return $this->error('No autenticado', 401);

        $pago = $this->findPagoByReferenciaOr404($referencia);

        $pago->estado_validacion = Pago::EST_INVALIDO; // 2
        $pago->id_admin_validador = $user->getKey();
        $pago->save();

        return $this->ok(null, 'Pago marcado como referencia inválida');
    }

    /** POST /admin/pago/{referencia}/generar-folio */
    public function generarFolio(Request $request, string $referencia): JsonResponse
    {
        $user = $request->user();
        if (!$user)
            return $this->error('No autenticado', 401);

        $pago = $this->findPagoByReferenciaOr404($referencia);

        if ((int) $pago->estado_validacion !== Pago::EST_VALIDADO) {
            return $this->error('No se puede generar folio: el pago no está validado', 422);
        }

        $asp = $pago->aspirante ?? Aspirante::find($pago->id_aspirantes);
        if (!$asp)
            return $this->error('Aspirante no encontrado', 404);

        if (empty($asp->folio_examen)) {
            $folio = FolioGenerator::generate();
            DB::transaction(function () use ($asp, $folio) {
                $asp->folio_examen = $folio;
                $asp->save();
            });

            FolioGenerado::dispatch($asp, $folio);

            return $this->ok(['folio' => $folio], 'Folio generado');
        }

        $folio = FolioGenerator::generate();

        DB::transaction(function () use ($asp, $folio) {
            $asp->folio_examen = $folio;
            $asp->save();
        });

        return $this->ok(['folio' => $folio], 'Folio generado');
    }
}
