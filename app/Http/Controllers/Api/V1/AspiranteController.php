<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AspiranteStoreRequest;
use App\Http\Requests\V1\AspiranteUpdateRequest;
use App\Http\Resources\V1\AspiranteResource;
use App\Http\Resources\V1\FolioResource;
use App\Models\Aspirante;
use App\Models\Pago;
use App\Services\ExamSyncService;
use App\Services\PaymentSuccessService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Mail\FolioGeneradoMail;
use Illuminate\Support\Facades\Mail;

class AspiranteController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $q = Aspirante::query()->with(['carrera']);

        // filtros opcionales: ?carrera=1&estatus=1&search=juan
        if ($request->has('carrera')) {
            $q->where('id_carrera', $request->integer('carrera'));
        }
        if ($request->has('estatus')) {
            $q->where('estatus', $request->integer('estatus'));
        }
        if ($search = $request->get('search')) {
            $q->where(function ($w) use ($search) {
                $w->where('nombre', 'like', "%$search%")
                    ->orWhere('ap_paterno', 'like', "%$search%")
                    ->orWhere('ap_materno', 'like', "%$search%");
            });
        }

        $aspirantes = $q->latest('id_aspirantes')->paginate(
            perPage: (int) $request->get('per_page', 15)
        );

        return $this->ok([
            'pagination' => [
                'current_page' => $aspirantes->currentPage(),
                'per_page' => $aspirantes->perPage(),
                'total' => $aspirantes->total(),
                'last_page' => $aspirantes->lastPage(),
            ],
            'data' => AspiranteResource::collection($aspirantes->items()),
        ]);
    }

    public function store(AspiranteStoreRequest $request)
    {
        $asp = Aspirante::create($request->validated());
        $asp->load('carrera');

        return $this->ok(new AspiranteResource($asp), 'Creado', 201);
    }

    public function show($aspirante)
    {
        $asp = Aspirante::query()
            ->with([
                'carrera',
                'bachillerato',
                'documentos.validador',
                'pagos.configuracion',
            ])
            ->where(function ($q) use ($aspirante) {
                $q->where('id_aspirantes', $aspirante)
                    ->orWhere('folio_examen', $aspirante)
                    ->orWhere('curp', $aspirante);
            })
            ->first();

        if (!$asp) {
            return response()->json([
                'success' => false,
                'message' => 'Aspirante no encontrado',
            ], 404);
        }

        return $this->ok(new AspiranteResource($asp));
    }

    public function update(AspiranteUpdateRequest $request, Aspirante $aspirante)
    {
        $aspirante->update($request->validated());
        $aspirante->load('carrera');

        return $this->ok(new AspiranteResource($aspirante), 'Actualizado');
    }

    public function destroy(Aspirante $aspirante)
    {
        $aspirante->delete();
        return $this->ok(null, 'Eliminado', 204);
    }

    public function checkFolio(Request $request): JsonResponse
    {
        $aspirante = $request->user();

        return response()->json([
            'folio' => $aspirante?->folio_examen ?: false,
        ]);
    }

    public function ensureFolio(Request $request, PaymentSuccessService $paymentSuccess): JsonResponse
    {
        $aspirante = $request->user();

        if (!$aspirante instanceof Aspirante) {
            return response()->json([
                'success' => false,
                'message' => 'Solo aspirantes pueden solicitar esta validación.',
            ], 403);
        }

        $aspirante->refresh();

        $folioBefore = $aspirante->folio_examen;
        $configId = (int) config('admissions.diagnostic_payment_config_id', 3);

        $pago = Pago::query()
            ->where('id_aspirantes', $aspirante->id_aspirantes)
            ->where('id_configuracion', $configId)
            ->where('estado_validacion', Pago::EST_VALIDADO)
            ->latest('fecha_pago')
            ->first();

        if ($pago) {
            $paymentSuccess->handle($pago);
            $aspirante->refresh();
        }

        $folio = $aspirante->folio_examen;

        return response()->json([
            'success' => true,
            'has_validated_payment' => (bool) $pago,
            'generated_now' => empty($folioBefore) && !empty($folio),
            'folio' => $folio ?: null,
            'progress_step' => (int) ($aspirante->progress_step ?? 1),
        ]);
    }

    public function progress(Request $request)
    {
        /** @var \App\Models\Aspirante $aspirante */
        $aspirante = $request->user();

        return response()->json([
            'success' => true,
            'step' => $aspirante->progress_step,
        ]);
    }

    public function updateProgress(Request $request)
    {
        /** @var \App\Models\Aspirante $aspirante */
        $aspirante = $request->user();

        $step = $request->input('step');
        if (!$step) {
            return response()->json(['success' => false, 'message' => 'Step requerido'], 400);
        }

        $aspirante->progress_step = $step;
        $aspirante->save();

        return response()->json([
            'success' => true,
            'step' => $aspirante->progress_step,
        ]);
    }

    public function adminUpdateProgress(Request $request, Aspirante $aspirante, ExamSyncService $examSync)
    {
        $validated = $request->validate([
            'step' => ['required', 'integer', 'min:-1', 'max:7'],
        ]);

        $previousStep = (int) ($aspirante->progress_step ?? 1);
        $aspirante->progress_step = $validated['step'];
        $aspirante->save();

        $aspirante->refresh();
        $this->handleManualStepNotification($aspirante, $previousStep, $examSync);
        $aspirante->refresh()->load(['carrera', 'bachillerato', 'documentos.validador', 'pagos.configuracion']);

        return $this->ok(new AspiranteResource($aspirante), 'Paso actualizado');
    }

    public function saveAcademicInfo(Request $request)
    {
        /** @var \App\Models\Aspirante $aspirante */
        $aspirante = $request->user();

        $validated = $request->validate([
            'id_bachillerato' => ['required', 'exists:bachilleratos,id_bachillerato'],
            'promedio_general' => ['required', 'numeric', 'min:0', 'max:10'],
            'id_carrera' => ['required', 'exists:carreras,id_carreras'],
        ]);

        $aspirante->id_bachillerato = $validated['id_bachillerato'];
        $aspirante->promedio_general = $validated['promedio_general'];
        $aspirante->id_carrera = $validated['id_carrera'];
        $aspirante->progress_step = 3;
        $aspirante->save();
        $aspirante->load(['bachillerato', 'carrera']);

        return $this->ok(new AspiranteResource($aspirante), 'Datos académicos guardados');
    }

    public function resendFolio(Request $request)
    {
        /** @var \App\Models\Aspirante $aspirante */
        $aspirante = $request->user();

        if (empty($aspirante->folio_examen)) {
            return response()->json([
                'success' => false,
                'message' => 'Aún no tienes folio asignado',
            ], 422);
        }

        if (empty($aspirante->email) || !filter_var($aspirante->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un correo registrado en tu perfil',
            ], 422);
        }

        // Enviar correo
        Mail::to($aspirante->email)->send(new FolioGeneradoMail($aspirante, $aspirante->folio_examen));

        return response()->json([
            'success' => true,
            'message' => 'Folio reenviado a tu correo',
        ]);
    }

        private function handleManualStepNotification(Aspirante $aspirante, int $previousStep, ExamSyncService $examSync): void
        {
            $currentStep = (int) ($aspirante->progress_step ?? 1);

            if ($currentStep === -1 && $previousStep !== -1) {
                $examSync->applyResult($aspirante, 'rechazado');
                return;
            }

            if ($currentStep >= 5 && $previousStep < 5) {
                $examSync->applyResult($aspirante, 'aprobado', $currentStep);
            }
        }

}
