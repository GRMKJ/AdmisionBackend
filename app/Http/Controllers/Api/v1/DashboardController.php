<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Aspirante;
use App\Models\Cita;
use App\Models\Documento;
use App\Models\Alumno;

class DashboardController extends Controller
{
public function stats(): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => [
            // Aspirantes que completaron el registro (step >= 1)
            'aspirantes_registrados' => Aspirante::where('progress_step', '>=', 1)->count(),

            // Aspirantes que están en paso 3 (pago validado, pendientes de examen)
            'pendientes_examen' => Aspirante::where('progress_step', 3)->count(),

            // Aspirantes que están en paso 5 (subieron docs pero aún sin validar)
            'pendientes_documentos' => Aspirante::where('progress_step', 5)->count(),

            // Aspirantes en paso 6 (ya inscritos como alumnos)
            'alumnos_inscritos' => Aspirante::where('progress_step', 6)->count(),
        ]
    ]);
}
}
