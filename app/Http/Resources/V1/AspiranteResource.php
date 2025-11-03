<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AspiranteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Mapea step -> ruta recomendada (para el frontend)
        $redirectTo = match ((int)($this->step ?? 1)) {
            1 => '/admision',
            2 => '/admision/pagoexamen',
            3 => '/admision/pagoexamen/status',
            4 => '/admision/documentos/subida',
            5 => '/admision/documentos/estado',
            6 => '/alumno/inicio',
            default => '/admision'
        };

        return [
            'id'          => $this->id_aspirantes,
            'curp'        => $this->curp,
            'nombre'      => $this->nombre,
            'ap_paterno'  => $this->ap_paterno,
            'ap_materno'  => $this->ap_materno,
            'id_carrera'  => $this->id_carrera,
            'estatus'     => (int) $this->estatus,
            'fecha_registro' => optional($this->fecha_registro)->toISOString(),
            // Progreso / estado
            'step'        => (int) ($this->step ?? 1),
            'payment_status'   => (int) ($this->payment_status ?? 0),
            'documents_status' => (int) ($this->documents_status ?? 0),
            // Sugerencia para frontend
            'redirect_to' => $redirectTo,
        ];
    }
}
