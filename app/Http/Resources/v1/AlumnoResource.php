<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlumnoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id_inscripcion,
            'matricula'            => $this->matricula,
            'fechaInscripcion'     => optional($this->fecha_inscripcion)->toDateString(),
            'fechaInicioClase'     => optional($this->fecha_inicio_clase)->toDateString(),
            'fechaFinClases'       => optional($this->fecha_fin_clases)->toDateString(),
            'correoInstituto'      => $this->correo_instituto,
            'numeroSeguroSocial'   => $this->numero_seguro_social,
            'estatus'              => (int) $this->estatus,
            'aspirante' => [
                'id'      => $this->aspirante?->id_aspirantes,
                'nombre'  => $this->aspirante?->nombre,
                'paterno' => $this->aspirante?->ap_paterno,
                'materno' => $this->aspirante?->ap_materno,
            ],
            'carrera' => [
                'id'     => $this->aspirante?->carrera?->id_carreras,
                'nombre' => $this->aspirante?->carrera?->carrera,
            ],
            'links' => [
                'self' => route('alumnos.show', ['alumno' => $this->id_inscripcion]),
            ],
        ];
    }
}
