<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DocumentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id_documentos,
            'pendientes'    => $this->pendientes,
            'archivoPath'   => $this->archivo_pat,
            'archivo_url' => $this->archivo_pat
                ? (method_exists(Storage::disk('public'), 'url')
                    ? Storage::disk('public')->url($this->archivo_pat)
                    : asset('storage/'.$this->archivo_pat))
                : null,
            'fechaRegistro' => optional($this->fecha_registro)->toDateString(),

            'revision' => [
                'estado'          => (int) $this->estado_validacion, // 0,1,2
                'observaciones'   => $this->observaciones,
                'fechaValidacion' => optional($this->fecha_validacion)?->toDateTimeString(),
                'validador'       => $this->validador ? [
                    'id'       => $this->validador->id_administrativo,
                    'nombre'   => $this->validador->nombre,
                    'numEmp'   => $this->validador->numero_empleado ?? null,
                ] : null,
            ],

            'aspirante' => [
                'id'     => $this->aspirante?->id_aspirantes,
                'nombre' => $this->aspirante?->nombre,
            ],
            'links' => [
                'self' => route('documentos.show', ['documento' => $this->id_documentos]),
            ],
        ];
    }
}
