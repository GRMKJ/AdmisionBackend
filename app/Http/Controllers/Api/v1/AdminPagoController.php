<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PagoDetalleResource;
use App\Models\Aspirante;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\FolioGenerado;

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
        $pago = $this->findPagoByReferenciaOr404($referencia);
        $asp  = $pago->aspirante ?? Aspirante::find($pago->id_aspirantes);

        return $this->ok(new PagoDetalleResource([
            'aspirante' => $asp,
            'pago'      => $pago,
        ]));
    }

    /** POST /admin/pago/{referencia}/validar */
    public function validar(Request $request, string $referencia): JsonResponse
    {
        $user = $request->user();
        if (!$user) return $this->error('No autenticado', 401);

        $pago = $this->findPagoByReferenciaOr404($referencia);

        // si ya está validado, idempotencia simple
        if ((int)$pago->estado_validacion === Pago::EST_VALIDADO) {
            return $this->ok(null, 'Pago ya estaba validado');
        }

        $pago->estado_validacion  = Pago::EST_VALIDADO; // 1
        $pago->id_admin_validador = $user->getKey();    // guarda el admin que valida
        $pago->save();

        return $this->ok(null, 'Pago validado');
    }

    /** POST /admin/pago/{referencia}/invalidar  (opcional) */
    public function invalidar(Request $request, string $referencia): JsonResponse
    {
        $user = $request->user();
        if (!$user) return $this->error('No autenticado', 401);

        $pago = $this->findPagoByReferenciaOr404($referencia);

        $pago->estado_validacion  = Pago::EST_INVALIDO; // 2
        $pago->id_admin_validador = $user->getKey();
        $pago->save();

        return $this->ok(null, 'Pago marcado como referencia inválida');
    }

    /** POST /admin/pago/{referencia}/generar-folio */
    public function generarFolio(Request $request, string $referencia): JsonResponse
    {
        $user = $request->user();
        if (!$user) return $this->error('No autenticado', 401);

        $pago = $this->findPagoByReferenciaOr404($referencia);

        // Reglas de negocio típicas
        if ((int)$pago->estado_validacion !== Pago::EST_VALIDADO) {
            return $this->error('No se puede generar folio: el pago no está validado', 422);
        }

        $asp = $pago->aspirante ?? Aspirante::find($pago->id_aspirantes);
        if (!$asp) return $this->error('Aspirante no encontrado', 404);

        // Si ya tiene folio, regresar el existente (idempotencia)
        if (empty($asp->folio_examen)) {
            $folio = $this->generarFolioUnico();
            DB::transaction(function () use ($asp, $folio) {
                $asp->folio_examen = $folio;
                $asp->save();
            });

            // 🔔 Dispara evento
            FolioGenerado::dispatch($asp, $folio);

            return $this->ok(['folio' => $folio], 'Folio generado');
        }

        // Generar folio único (ejemplo UTH-YYYY-XXXXXX)
        $folio = $this->generarFolioUnico();

        DB::transaction(function () use ($asp, $folio) {
            $asp->folio_examen = $folio;
            $asp->save();
        });

        return $this->ok(['folio' => $folio], 'Folio generado');
    }

    /** Genera un folio garantizando unicidad */
    private function generarFolioUnico(): string
    {
        $year = now()->format('Y');
        do {
            $rand = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $folio = "UTH-{$year}-{$rand}";
            $exists = Aspirante::where('folio_examen', $folio)->exists();
        } while ($exists);

        return $folio;
    }
}
