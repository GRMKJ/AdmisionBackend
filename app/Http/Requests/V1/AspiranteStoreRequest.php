<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class AspiranteStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_carrera'     => ['required','exists:carreras,id_carreras'],
            'nombre'         => ['required','string','max:150'],
            'ap_paterno'     => ['required','string','max:150'],
            'ap_materno'     => ['nullable','string','max:150'],
            'telefono'       => ['nullable','string','max:20'],
            'fecha_registro' => ['nullable','date'],
            'estatus'        => ['nullable','integer','in:0,1'],
        ];
    }
}
