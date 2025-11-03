<?php
namespace App\Http\Controllers\Api\V1;

use App\Models\Aspirante;
use App\Models\Bachillerato;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Pago;

class AdministradorController extends Controller
{
    public function aspirantes(): JsonResponse
    {
        return response()->json([
        'success' => true,
        'data' => [
            'todos' => Aspirante::where('progress_step', '>=', 1)
                ->with('pagos') // 🔹 incluir pago
                ->get(['id_aspirantes','folio_examen','nombre','ap_paterno','ap_materno','progress_step']),

            'con_pago' => Aspirante::where('progress_step', 3)
                ->with('pagos') // 🔹 incluir pago
                ->get(['id_aspirantes','folio_examen','nombre','ap_paterno','ap_materno','progress_step']),

            'con_documentos' => Aspirante::where('progress_step', 5)
                ->with('pagos')
                ->get(['id_aspirantes','folio_examen','nombre','ap_paterno','ap_materno','progress_step']),

            'listos_inscripcion' => Aspirante::where('progress_step', 6)
                ->with('pagos')
                ->get(['id_aspirantes','folio_examen','nombre','ap_paterno','ap_materno','progress_step']),
        ]
    ]);
    }

public function aspirantePorReferencia($referencia): JsonResponse
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

public function validarPago($referencia): JsonResponse
{
    $pago = Pago::where('referencia', $referencia)->firstOrFail();
    $aspirante = $pago->aspirante;

    $pago->estado_validacion = 1; // validado
    $pago->save();

    $aspirante->progress_step = 3;
    $aspirante->save();

    return response()->json(['success' => true, 'message' => 'Pago validado correctamente']);
}

public function generarFolio($referencia): JsonResponse
{
    $pago = Pago::where('referencia', $referencia)->firstOrFail();
    $aspirante = $pago->aspirante;

    if (!$aspirante->folio_examen) {
        $aspirante->folio_examen = 'ASP' . str_pad($aspirante->id_aspirantes, 3, '0', STR_PAD_LEFT);
        $aspirante->save();
    }

    return response()->json([
        'success' => true,
        'folio' => $aspirante->folio_examen,
        'message' => 'Folio generado'
    ]);
}


}
