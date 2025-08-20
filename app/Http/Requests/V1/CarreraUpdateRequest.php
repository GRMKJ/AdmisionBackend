<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CarreraUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'carrera'     => ['sometimes','string','max:200'],
            'duracion'    => ['nullable','string','max:100'],
            'descripcion' => ['nullable','string'],
            'estatus'     => ['nullable','integer','in:0,1'],
        ];
    }
}
