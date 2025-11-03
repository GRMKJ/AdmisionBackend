<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentoRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id_revision,
            'estado'       => (int) $this->estado, // 0,1,2
            'observaciones'=> $this->observaciones,
            'fechaEvento'  => optional($this->fecha_evento)?->toDateTimeString(),
            'validador'    => $this->validador ? [
                'id'       => $this->validador->id_administrativo,
                'nombre'   => $this->validador->nombre,
                'numEmp'   => $this->validador->numero_empleado ?? null,
            ] : null,
        ];
    }
}
