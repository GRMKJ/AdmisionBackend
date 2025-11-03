<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BachilleratoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id_bachillerato,
            'nombre'    => $this->nombre,
            'municipio' => $this->municipio,
            'estado'    => $this->estado,
            'aspirantes'=> $this->whenLoaded('aspirantes'),
        ];
    }
}
