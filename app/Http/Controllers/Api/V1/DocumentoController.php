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
use Illuminate\Http\UploadedFile;

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

    $file = $request->file('archivo');
    $ocrAnalysis = $this->analyzeDocumentOcr($file, $documentName, $aspirante);
    if ($ocrAnalysis && !empty($ocrAnalysis['reject'])) {
        return response()->json([
            'success' => false,
            'message' => $ocrAnalysis['message'] ?? 'El documento no coincide con tus datos, verifica e inténtalo nuevamente.',
        ], 422);
    }

    $path = $file->store("documentos/{$aspirante->id_aspirantes}", 'public');

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


    if ($ocrAnalysis) {
        $this->applyOcrAnalysis($doc, $ocrAnalysis);
    }

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

        $replacementAnalysis = null;
        // reemplazar archivo si viene uno nuevo
        if ($request->hasFile('archivo')) {
            $documento->loadMissing('aspirante');
            $replacementAnalysis = $this->analyzeDocumentOcr($request->file('archivo'), $documento->nombre, $documento->aspirante);
            if ($replacementAnalysis && !empty($replacementAnalysis['reject'])) {
                return response()->json([
                    'success' => false,
                    'message' => $replacementAnalysis['message'] ?? 'El documento no coincide con los datos registrados.',
                ], 422);
            }
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

        if ($replacementAnalysis) {
            $this->applyOcrAnalysis($documento, $replacementAnalysis);
        }

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

        $documento->loadMissing('aspirante');
        $analysis = $this->analyzeDocumentOcr($request->file('archivo'), $documento->nombre, $documento->aspirante);
        if ($analysis && !empty($analysis['reject'])) {
            return response()->json([
                'success' => false,
                'message' => $analysis['message'] ?? 'El documento no coincide con los datos registrados.',
            ], 422);
        }

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

        if ($analysis) {
            $this->applyOcrAnalysis($documento, $analysis);
        }

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

    private function analyzeDocumentOcr(?UploadedFile $file, ?string $documentName, ?Aspirante $aspirante): ?array
    {
        if (!$file || !$aspirante) {
            return null;
        }

        $ocrPayload = $this->callOcrService($file);
        if (!$ocrPayload) {
            return null;
        }

        $analysis = $this->evaluateOcrResult($documentName, $ocrPayload, $aspirante);
        if (!$analysis) {
            return null;
        }

        return $analysis;
    }

    private function applyOcrAnalysis(Documento $documento, array $analysis): void
    {
        if (!array_key_exists('estado', $analysis) && empty($analysis['observaciones'])) {
            return;
        }

        $estado = $analysis['estado'] ?? $documento->estado_validacion;
        $observaciones = trim((string) ($analysis['observaciones'] ?? ''));

        $documento->forceFill([
            'estado_validacion' => $estado,
            'observaciones' => $observaciones ?: $documento->observaciones,
            'fecha_validacion' => $estado === Documento::ESTADO_VALIDADO_AUTOMATICO ? now() : $documento->fecha_validacion,
            'id_validador' => $estado === Documento::ESTADO_VALIDADO_AUTOMATICO ? null : $documento->id_validador,
        ])->save();

        DocumentoRevision::create([
            'id_documentos' => $documento->id_documentos,
            'id_validador' => null,
            'estado' => $estado,
            'observaciones' => $observaciones ?: 'Resultado OCR sin detalles',
            'fecha_evento' => now(),
        ]);
    }

    private function callOcrService(UploadedFile $file): ?array
    {
        try {
            $response = Http::timeout(15)
                ->attach('file', $file->get(), $file->getClientOriginalName())
                ->post('http://127.0.0.1:9010/ocr');

            if (!$response->ok()) {
                return null;
            }

            $json = $response->json();
            if (!is_array($json) || empty($json['ok'])) {
                return null;
            }

            return $json;
        } catch (\Throwable $th) {
            report($th);
            return null;
        }
    }

    private function evaluateOcrResult(?string $documentName, array $ocrPayload, Aspirante $aspirante): ?array
    {
        $label = strtoupper(trim((string) $documentName));

        if ($this->looksLikeCurpDocument($label)) {
            return $this->evaluateCurpDocument($ocrPayload, $aspirante);
        }

        if ($this->looksLikeActaDocument($label)) {
            return $this->evaluateActaDocument($ocrPayload, $aspirante);
        }

        if ($this->looksLikeNssDocument($label)) {
            return $this->evaluateNssDocument($ocrPayload);
        }

        if ($this->looksLikeAddressDocument($label)) {
            return $this->evaluateAddressDocument($ocrPayload);
        }

        return null;
    }

    private function evaluateCurpDocument(array $ocrPayload, Aspirante $aspirante): array
    {
        $curp = strtoupper((string) $aspirante->curp);
        $ocrCurps = $this->normalizeCollection($ocrPayload['matches']['CURP'] ?? []);
        $curpMatch = $curp && in_array($curp, $ocrCurps, true);
        $nameMatch = $this->ocrContainsAspiranteName($ocrPayload, $aspirante);

        if (!$curpMatch) {
            return [
                'reject' => true,
                'message' => 'El CURP del documento no coincide con el registrado. Verifica tu archivo e intenta nuevamente.',
            ];
        }

        $estado = ($curpMatch && $nameMatch)
            ? Documento::ESTADO_VALIDADO_AUTOMATICO
            : Documento::ESTADO_PENDIENTE_MANUAL;

        $observaciones = sprintf(
            "OCR CURP detectado: %s\nNombre coincide: %s\nCURP OCR: %s",
            $curpMatch ? 'sí' : 'no',
            $nameMatch ? 'sí' : 'no',
            $ocrCurps ? implode(', ', $ocrCurps) : 'Sin coincidencias'
        );

        return [
            'estado' => $estado,
            'observaciones' => $observaciones,
        ];
    }

    private function evaluateActaDocument(array $ocrPayload, Aspirante $aspirante): array
    {
        $text = $this->normalizeValue($ocrPayload['text'] ?? '');
        $hasActaKeyword = $this->collectionHasKeyword($ocrPayload['matches']['ACTA_KEYWORDS'] ?? [])
            || Str::contains($text, 'ACTA')
            || Str::contains($text, 'NACIMIENTO');

        $curp = strtoupper((string) $aspirante->curp);
        $ocrCurps = $this->normalizeCollection($ocrPayload['matches']['CURP'] ?? []);
        $curpMatch = $curp && in_array($curp, $ocrCurps, true);
        $nameMatch = $this->ocrContainsAspiranteName($ocrPayload, $aspirante);

        $isValid = $nameMatch && ($hasActaKeyword || $curpMatch);
        $estado = $isValid ? Documento::ESTADO_VALIDADO_AUTOMATICO : Documento::ESTADO_PENDIENTE_MANUAL;

        $observaciones = sprintf(
            "Nombre coincide: %s\nActa detectada: %s\nCURP presente: %s",
            $nameMatch ? 'sí' : 'no',
            $hasActaKeyword ? 'sí' : 'no',
            $curpMatch ? 'sí' : 'no'
        );

        return [
            'estado' => $estado,
            'observaciones' => $observaciones,
        ];
    }

    private function evaluateNssDocument(array $ocrPayload): array
    {
        $nssList = array_unique(array_filter(array_map('trim', $ocrPayload['matches']['NSS'] ?? [])));
        $observaciones = $nssList
            ? 'NSS: '.implode(', ', $nssList)
            : 'No se detectó NSS en el OCR.';

        return [
            'estado' => Documento::ESTADO_PENDIENTE_MANUAL,
            'observaciones' => $observaciones,
        ];
    }

    private function evaluateAddressDocument(array $ocrPayload): array
    {
        $addresses = array_unique(array_filter(array_map('trim', $ocrPayload['matches']['ADDRESS'] ?? [])));
        $text = $this->normalizeValue($ocrPayload['text'] ?? '');
        $hasTotalKeyword = Str::contains($text, 'TOTAL') || Str::contains($text, 'IMPORTE');
        $serviceKeywords = ['GAS', 'TELEFONO', 'INTERNET', 'LUZ', 'AGUA'];
        $hasServiceKeyword = false;
        foreach ($serviceKeywords as $keyword) {
            if (Str::contains($text, $keyword)) {
                $hasServiceKeyword = true;
                break;
            }
        }

        $parts = [];
        $parts[] = $addresses
            ? "Direcciones detectadas:\n- ".implode("\n- ", $addresses)
            : 'No se detectaron domicilios en el OCR.';
        $parts[] = 'Total/Importe detectado: '.($hasTotalKeyword ? 'sí' : 'no');
        $parts[] = 'Servicio detectado (gas/teléfono/internet/luz/agua): '.($hasServiceKeyword ? 'sí' : 'no');
        $observaciones = implode("\n", $parts);

        return [
            'estado' => Documento::ESTADO_PENDIENTE_MANUAL,
            'observaciones' => $observaciones,
        ];
    }

    private function looksLikeCurpDocument(string $label): bool
    {
        return Str::contains($label, 'CURP');
    }

    private function looksLikeActaDocument(string $label): bool
    {
        return Str::contains($label, 'ACTA');
    }

    private function looksLikeNssDocument(string $label): bool
    {
        return Str::contains($label, 'SEGURO SOCIAL') || Str::contains($label, 'NSS');
    }

    private function looksLikeAddressDocument(string $label): bool
    {
        return Str::contains($label, 'DOMICILIO');
    }

    private function normalizeCollection(array $values): array
    {
        return array_values(array_filter(array_map(function ($value) {
            return strtoupper($this->normalizeValue((string) $value));
        }, $values)));
    }

    private function collectionHasKeyword(array $values): bool
    {
        foreach ($values as $value) {
            if (Str::length(trim($value))) {
                return true;
            }
        }
        return false;
    }

    private function ocrContainsAspiranteName(array $ocrPayload, Aspirante $aspirante): bool
    {
        $aspiranteName = $this->buildAspiranteName($aspirante);
        $nameCandidates = $ocrPayload['matches']['NAME'] ?? [];

        foreach ($nameCandidates as $candidate) {
            if ($this->namesRoughlyMatch($aspiranteName, $candidate)) {
                return true;
            }
        }

        $normalizedText = $this->normalizeValue($ocrPayload['text'] ?? '');
        $tokens = $this->tokenizeName($aspiranteName);
        if (!$normalizedText || empty($tokens)) {
            return false;
        }

        $hits = 0;
        foreach ($tokens as $token) {
            if (Str::contains($normalizedText, $token)) {
                $hits++;
            }
        }

        return $hits >= min(3, count($tokens));
    }
}
