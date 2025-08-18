<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\PagoStoreRequest;
use App\Http\Requests\V1\PagoUpdateRequest;
use App\Http\Resources\V1\PagoResource;
use App\Models\Pago;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Aspirante;
use App\Models\Bachillerato;

class PagoController extends Controller
{
    use ApiResponse;

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

    private function buildFileName(string $original): string
    {
        $name = pathinfo($original, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        return Str::slug($name) . '-' . time() . '.' . $ext;
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
