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
use Illuminate\Support\Str;
use App\Http\Requests\V1\DocumentoReviewRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\V1\DocumentoRevisionResource;
use App\Models\DocumentoRevision;
use App\Models\Aspirante;

class DocumentoController extends Controller
{
    use ApiResponse;

public function store(Request $request)
{
    $request->validate([
        'documento' => 'required|string',
        'archivo'   => 'required|file|mimes:pdf|max:2048',
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
            'estado_validacion' => 0,
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
                'estado_validacion' => 0,
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
            'archivo' => ['required','file','max:5120','mimes:pdf,jpg,jpeg,png,doc,docx'],
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
            'estado_validacion' => 0,
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
            'estado_validacion' => 0,
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
}
