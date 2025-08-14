<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class AspiranteUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_carrera'     => ['sometimes','exists:carreras,id_carreras'],
            'nombre'         => ['sometimes','string','max:150'],
            'ap_paterno'     => ['sometimes','string','max:150'],
            'ap_materno'     => ['nullable','string','max:150'],
            'telefono'       => ['nullable','string','max:20'],
            'fecha_registro' => ['nullable','date'],
            'estatus'        => ['nullable','integer','in:0,1'],
        ];
    }
}
