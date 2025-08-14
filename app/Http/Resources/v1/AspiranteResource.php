<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AspiranteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id_aspirantes,
            'nombre'        => $this->nombre,
            'ap_paterno'    => $this->ap_paterno,
            'ap_materno'    => $this->ap_materno,
            'telefono'      => $this->telefono,
            'fechaRegistro' => optional($this->fecha_registro)->toDateString(),
            'estatus'       => (int) $this->estatus,
            'carrera'       => [
                'id'   => $this->carrera?->id_carreras,
                'nombre' => $this->carrera?->carrera,
            ],
            // relaciones opcionales
            'links' => [
                'self' => route('aspirantes.show', ['aspirante' => $this->id_aspirantes]),
            ],
        ];
    }
}
