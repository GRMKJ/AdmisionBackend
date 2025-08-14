<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarreraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id_carreras,
            'carrera'     => $this->carrera,
            'duracion'    => $this->duracion,
            'descripcion' => $this->descripcion,
            'estatus'     => (int) $this->estatus,
            'links'       => [
                'self' => route('carreras.show', ['carrera' => $this->id_carreras]),
            ],
        ];
    }
}
