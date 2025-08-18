<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarreraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id_carreras,
            'clave'=> $this->clave ?? null,
            'nombre' => $this->nombre,
            'nivel'  => $this->nivel ?? null,
            'activo' => (bool) ($this->activo ?? 1),
        ];
    }
}
