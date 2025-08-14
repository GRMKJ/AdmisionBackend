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

class DocumentoController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $q = Documento::query()->with('aspirante');

        if ($request->filled('id_aspirantes')) {
            $q->where('id_aspirantes', $request->integer('id_aspirantes'));
        }
        if ($s = $request->get('search')) {
            $q->where('pendientes', 'like', "%$s%");
        }

        $rows = $q->latest('id_documentos')->paginate((int) $request->get('per_page', 15));

        return $this->ok([
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
            'data' => DocumentoResource::collection($rows->items()),
        ]);
    }

    public function store(DocumentoStoreRequest $request)
    {
        $data = $request->validated();

        // manejar archivo si viene
        $path = null;
        if ($request->hasFile('archivo')) {
            $dir = 'docs/'.$data['id_aspirantes'];
            $path = $request->file('archivo')->storeAs(
                $dir,
                $this->buildFileName($request->file('archivo')->getClientOriginalName()),
                'public'
            );
            $data['archivo_pat'] = $path;
        }

        $row = Documento::create($data);
        $row->load('aspirante');

        return $this->ok(new DocumentoResource($row), 'Creado', 201);
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

    /**
     * Endpoint dedicado: subir/reemplazar solo el archivo
     * POST /v1/documentos/{documento}/archivo
     */
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


    /**
     * Endpoint dedicado: borrar solo el archivo (conservar registro)
     * DELETE /v1/documentos/{documento}/archivo
     */
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
            'estado_validacion' => $request->integer('estado'), // 1 o 2
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
        // Solo administrativos o el dueño del documento (si así lo deseas)
        // Aquí lo dejo abierto a cualquier autenticado; añade abilities si quieres.
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
