<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class AlumnoStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_aspirantes'        => ['required','exists:aspirantes,id_aspirantes'],
            'fecha_inscripcion'    => ['nullable','date'],
            'nombre_carrera'       => ['nullable','string','max:200'],
            'matricula'            => ['nullable','string','max:50'],
            'fecha_inicio_clase'   => ['nullable','date'],
            'fecha_fin_clases'     => ['nullable','date','after_or_equal:fecha_inicio_clase'],
            'correo_instituto'     => ['nullable','email','max:150'],
            'numero_seguro_social' => ['nullable','string','max:50'],
            'estatus'              => ['nullable','integer','in:0,1'],
        ];
    }
}
