<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\DocumentoStoreRequest;
use App\Http\Requests\V1\DocumentoUpdateRequest;
use App\Http\Resources\V1\DocumentoResource;
use App\Models\Documento;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Http\Requests\V1\DocumentoReviewRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\V1\DocumentoRevisionResource;
use App\Models\DocumentoRevision;
use App\Models\Aspirante;
use App\Models\Alumno;

class DocumentoController extends Controller
{
    use ApiResponse;

    private const INSCRIPCION_DOC_NAME = 'Pago de Inscripción y Orden de Cobro';
    private const INSCRIPCION_EXPECTED_CONCEPT = 'CUOTA DE INSCRIPCION O REINSCRIPCION POR CUATRIMESTRE UNIV. TEC. HUEJOTZINGO';
    private const PHOTO_DOC_NAME = 'Foto Tamaño Infantil';

public function store(Request $request)
{
    $documentName = $request->string('documento')->toString();
    $request->validate([
        'documento' => 'required|string',
        'archivo'   => $this->buildUploadRules($documentName),
    ]);

    $aspirante = $request->user(); // ✅ ya es aspirante autenticado
    if (!$aspirante) {
        return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
    }

    $path = $request->file('archivo')->store("documentos/{$aspirante->id_aspirantes}", 'public');

    $doc = Documento::updateOrCreate(
        [
            'id_aspirantes' => $aspirante->id_aspirantes,
            'nombre'        => $request->documento,
        ],
        [
            'archivo_pat'       => $path,
            'fecha_registro'    => now(),
            'estado_validacion' => $this->pendingValidationState($documentName),
        ]
    );

    return response()->json([
        'success'   => true,
        'documento' => $doc,
    ]);
}


    public function index(Request $request)
    {
        $user = $request->user();

        // En este proyecto, el PK de Aspirante es `id_aspirantes` (no `id`).
        // Usar el campo correcto evita consultas vacías.
        $aspiranteId = $user->id_aspirantes ?? $user->id ?? null;
        if (!$aspiranteId) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo determinar el ID del aspirante.'
            ], 400);
        }

        $docs = Documento::where('id_aspirantes', $aspiranteId)->get();

        return response()->json(['success' => true, 'documentos' => $docs]);
    }

    public function show(Documento $documento)
    {
        $documento->load('aspirante');
        return $this->ok(new DocumentoResource($documento));
    }

    public function update(DocumentoUpdateRequest $request, Documento $documento)
    {
        $data = $request->validated();
        $resetValidation = false;

        // eliminar archivo si delete_file=1  (opcional: también resetea)
        if (!empty($data['delete_file']) && $documento->archivo_pat) {
            Storage::disk('public')->delete($documento->archivo_pat);
            $data['archivo_pat'] = null;
            $resetValidation = true; // <- si quieres que borrar también resetee
        }

        // reemplazar archivo si viene uno nuevo
        if ($request->hasFile('archivo')) {
            // borra el anterior si existía
            if ($documento->archivo_pat) {
                Storage::disk('public')->delete($documento->archivo_pat);
            }
            $dir = 'docs/'.($data['id_aspirantes'] ?? $documento->id_aspirantes);
            $path = $request->file('archivo')->storeAs(
                $dir,
                $this->buildFileName($request->file('archivo')->getClientOriginalName()),
                'public'
            );
            $data['archivo_pat'] = $path;
            $resetValidation = true; // <- archivo nuevo => reset
        }

        // aplica cambios básicos
        $documento->update($data);

        // si hubo cambio de archivo => resetear validación + AUDITORÍA
        if ($resetValidation) {
            $documento->forceFill([
                'estado_validacion' => $this->pendingValidationState($documento->nombre),
                'observaciones'     => null,
                'fecha_validacion'  => null,
                'id_validador'      => null,
            ])->save();

            DocumentoRevision::create([
                'id_documentos' => $documento->id_documentos,
                'id_validador'  => auth()->check() ? auth()->id() : null, // si lo ejecuta un admin autenticado
                'estado'        => 0,
                'observaciones' => 'Archivo actualizado: validación reiniciada.',
                'fecha_evento'  => now(),
            ]);
        }

        $documento->load(['aspirante','validador']);

        return $this->ok(new DocumentoResource($documento), 'Actualizado');
    }

    public function destroy(Documento $documento)
    {
        // borra el archivo físico si existe
        if ($documento->archivo_pat) {
            Storage::disk('public')->delete($documento->archivo_pat);
        }
        $documento->delete();
        return $this->ok(null, 'Eliminado', 204);
    }


    public function uploadFile(Request $request, Documento $documento)
    {
        $request->validate([
            'archivo' => $this->buildUploadRules($documento->nombre),
        ]);

        // borra anterior si había
        if ($documento->archivo_pat) {
            Storage::disk('public')->delete($documento->archivo_pat);
        }

        $dir = 'docs/'.$documento->id_aspirantes;
        $path = $request->file('archivo')->storeAs(
            $dir,
            $this->buildFileName($request->file('archivo')->getClientOriginalName()),
            'public'
        );

        // guarda nuevo path
        $documento->update(['archivo_pat' => $path]);

        // RESET de validación + AUDITORÍA
        $documento->forceFill([
            'estado_validacion' => $this->pendingValidationState($documento->nombre),
            'observaciones'     => null,
            'fecha_validacion'  => null,
            'id_validador'      => null,
        ])->save();

        DocumentoRevision::create([
            'id_documentos' => $documento->id_documentos,
            'id_validador'  => auth()->check() ? auth()->id() : null,
            'estado'        => 0,
            'observaciones' => 'Archivo actualizado: validación reiniciada.',
            'fecha_evento'  => now(),
        ]);

        $documento->load(['aspirante','validador']);

        return $this->ok(new DocumentoResource($documento), 'Archivo subido');
    }


    public function deleteFile(Documento $documento)
    {
        if (!$documento->archivo_pat) {
            return $this->error('No hay archivo para borrar', 422);
        }
        Storage::disk('public')->delete($documento->archivo_pat);
        $documento->update(['archivo_pat' => null]);

        // RESET + AUDITORÍA (opcional)
        $documento->forceFill([
            'estado_validacion' => $this->pendingValidationState($documento->nombre),
            'observaciones'     => null,
            'fecha_validacion'  => null,
            'id_validador'      => null,
        ])->save();

        DocumentoRevision::create([
            'id_documentos' => $documento->id_documentos,
            'id_validador'  => auth()->check() ? auth()->id() : null,
            'estado'        => 0,
            'observaciones' => 'Archivo eliminado: validación reiniciada.',
            'fecha_evento'  => now(),
        ]);

        $documento->load(['aspirante','validador']);

        return $this->ok(new DocumentoResource($documento), 'Archivo borrado');
    }

    private function buildFileName(string $original): string
    {
        $name = pathinfo($original, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        return Str::slug($name).'-'.time().'.'.$ext;
    }

    public function review(DocumentoReviewRequest $request, Documento $documento)
    {
        if (!auth()->user()->tokenCan('role:administrativo')) {
            return $this->error('No autorizado', 403);
        }

        $documento->update([
            'estado_validacion' => $request->integer('estado'),
            'observaciones'     => $request->input('observaciones'),
            'fecha_validacion'  => now(),
            'id_validador'      => auth()->id(),
        ]);

        // AUDITORÍA
        DocumentoRevision::create([
            'id_documentos' => $documento->id_documentos,
            'id_validador'  => auth()->id(),
            'estado'        => $request->integer('estado'),
            'observaciones' => $request->input('observaciones'),
            'fecha_evento'  => now(),
        ]);

        if ($request->integer('estado') === Documento::ESTADO_VALIDADO_MANUAL) {
            $this->syncAlumnoPhoto($documento);
        }

        $documento->load(['aspirante','validador']);
        return $this->ok(new DocumentoResource($documento), 'Revisión registrada');
    }
    public function history(Request $request, Documento $documento)
    {
        $q = $documento->revisiones()->with('validador');

        // ?per_page=15
        $perPage = (int) $request->get('per_page', 20);
        $p = $q->paginate($perPage);

        return $this->ok([
            'pagination' => [
                'current_page' => $p->currentPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'last_page'    => $p->lastPage(),
            ],
            'data' => DocumentoRevisionResource::collection($p->items()),
        ]);
    }

    public function adminManualValidate(Request $request, Documento $documento)
    {
        if (!auth()->user()?->tokenCan('role:administrativo')) {
            return $this->error('No autorizado', 403);
        }

        $data = $request->validate([
            'comentario' => ['required', 'string', 'min:5'],
            'original_fisico' => ['sometimes', 'boolean'],
            'copia_fisico' => ['sometimes', 'boolean'],
        ]);

        $documento->forceFill([
            'estado_validacion' => Documento::ESTADO_VALIDADO_MANUAL,
            'observaciones' => $data['comentario'],
            'fecha_validacion' => now(),
            'id_validador' => auth()->id(),
        ])->save();

        $detalleFisico = sprintf(
            'Original físico: %s · Copia física: %s',
            !empty($data['original_fisico']) ? 'Sí' : 'No',
            !empty($data['copia_fisico']) ? 'Sí' : 'No'
        );

        DocumentoRevision::create([
            'id_documentos' => $documento->id_documentos,
            'id_validador' => auth()->id(),
            'estado' => Documento::ESTADO_VALIDADO_MANUAL,
            'observaciones' => trim($data['comentario'] . ' · ' . $detalleFisico),
            'fecha_evento' => now(),
        ]);

        $this->syncAlumnoPhoto($documento);
        $documento->load(['aspirante','validador']);

        return $this->ok(new DocumentoResource($documento), 'Validación manual registrada');
    }

    public function validateInscripcionReference(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'min:6', 'max:64'],
        ]);

        $aspirante = $request->user();

        if (!$aspirante instanceof Aspirante) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo identificar al aspirante autenticado.',
            ]);
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->post($this->inscripcionValidatorUrl(), [
                    'reference' => $data['reference'],
                ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo contactar al servicio de validación. Intenta nuevamente en unos minutos.',
            ]);
        }

        if (!$response->ok()) {
            return response()->json([
                'success' => false,
                'message' => 'El servicio de validación no respondió correctamente. Vuelve a intentarlo más tarde.',
            ]);
        }

        $payload = $response->json();
        $validated = (bool)($payload['validated'] ?? false);

        if (!$validated) {
            return response()->json([
                'success' => false,
                'message' => 'Tu referencia aún no ha sido validada. La volveremos a consultar en las próximas horas y recibirás un correo cuando cambie el estado. También puedes intentarlo nuevamente desde esta pantalla.',
            ]);
        }

        $returnedName = $payload['name'] ?? '';
        $concept = $payload['concept'] ?? '';

        $aspiranteName = $this->buildAspiranteName($aspirante);
        if (!$this->namesRoughlyMatch($aspiranteName, $returnedName)) {
            return response()->json([
                'success' => false,
                'message' => 'La referencia pertenece a un nombre diferente al registrado. Verifica que capturaste la referencia correcta.',
            ]);
        }

        if (!$this->conceptLooksValid($concept)) {
            return response()->json([
                'success' => false,
                'message' => 'El concepto del pago no coincide con la cuota de inscripción esperada. Revisa tu comprobante e intenta más tarde.',
            ]);
        }

        $documento = Documento::firstOrCreate(
            [
                'id_aspirantes' => $aspirante->id_aspirantes,
                'nombre' => self::INSCRIPCION_DOC_NAME,
            ],
            [
                'fecha_registro' => now(),
                'estado_validacion' => 0,
            ]
        );

        $observaciones = sprintf(
            "Nombre validado: %s\nConcepto: %s",
            $returnedName ?: 'SIN NOMBRE',
            $concept ?: 'SIN CONCEPTO'
        );

        $documento->forceFill([
            'estado_validacion' => Documento::ESTADO_VALIDADO_AUTOMATICO,
            'observaciones' => $observaciones,
            'fecha_validacion' => now(),
            'id_validador' => null,
        ])->save();

        DocumentoRevision::create([
            'id_documentos' => $documento->id_documentos,
            'id_validador' => null,
            'estado' => Documento::ESTADO_VALIDADO_AUTOMATICO,
            'observaciones' => 'Validación automática por referencia. ' . $observaciones,
            'fecha_evento' => now(),
        ]);

        $documento->load(['aspirante', 'validador']);

        return response()->json([
            'success' => true,
            'message' => 'Referencia validada correctamente. Guardamos la evidencia en tu expediente.',
            'documento' => new DocumentoResource($documento),
        ]);
    }

    private function buildUploadRules(?string $documentName): array
    {
        if ($this->isPhotoDocument($documentName)) {
            return ['required', 'file', 'mimes:jpg,jpeg', 'max:5120'];
        }

        return ['required', 'file', 'mimes:pdf', 'max:2048'];
    }

    private function pendingValidationState(?string $documentName): int
    {
        return $this->isPhotoDocument($documentName)
            ? Documento::ESTADO_PENDIENTE_MANUAL
            : Documento::ESTADO_PENDIENTE;
    }

    private function isPhotoDocument(?string $documentName): bool
    {
        return strcasecmp(trim((string) $documentName), self::PHOTO_DOC_NAME) === 0;
    }

    private function syncAlumnoPhoto(Documento $documento): void
    {
        if (!$this->isPhotoDocument($documento->nombre) || empty($documento->archivo_pat)) {
            return;
        }

        Alumno::where('id_aspirantes', $documento->id_aspirantes)
            ->update(['foto' => $documento->archivo_pat]);
    }

    private function inscripcionValidatorUrl(): string
    {
        return config('services.inscripcion_validator.url', 'http://127.0.0.1:9005/validate');
    }

    private function buildAspiranteName(Aspirante $aspirante): string
    {
        return collect([
            $aspirante->nombre,
            $aspirante->ap_paterno,
            $aspirante->ap_materno,
        ])->filter(fn ($value) => filled($value))->implode(' ');
    }

    private function normalizeValue(?string $value): string
    {
        if (!$value) {
            return '';
        }

        return Str::of($value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9 ]/', ' ')
            ->squish()
            ->value();
    }

    private function conceptLooksValid(?string $concept): bool
    {
        if (!$concept) {
            return false;
        }

        $normalized = $this->normalizeValue($concept);

        return !empty($normalized)
            && Str::contains($normalized, 'CUOTA DE INSCRIPCION')
            && Str::contains($normalized, 'HUEJOTZINGO')
            && Str::contains($normalized, 'REINSCRIPCION');
    }

    private function namesRoughlyMatch(string $aspiranteName, ?string $returnedName): bool
    {
        if (!$returnedName) {
            return false;
        }


        $aspTokens = $this->tokenizeName($aspiranteName);
        $returnedTokens = $this->tokenizeName($returnedName);

        if (empty($aspTokens) || empty($returnedTokens)) {
            return false;
        }

        $matches = 0;
        foreach ($aspTokens as $token) {
            if (in_array($token, $returnedTokens, true)) {
                $matches++;
            }
        }

        $requiredMatches = min(3, count($aspTokens));

        return $matches >= $requiredMatches;
    }

    private function tokenizeName(?string $value): array
    {
        $normalized = $this->normalizeValue($value);
        if (empty($normalized)) {
            return [];
        }

        return array_values(array_filter(array_unique(explode(' ', $normalized))));
    }
}
