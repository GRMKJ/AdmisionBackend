<?php

namespace App\Http\Requests\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AspiranteRegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_carrera' => ['required', 'exists:carreras,id_carreras'],
            'nombre'     => ['required', 'string', 'max:150'],
            'ap_paterno' => ['required', 'string', 'max:150'],
            'ap_materno' => ['nullable', 'string', 'max:150'],
            'curp'       => ['required', 'string', 'max:18', 'unique:aspirantes,curp'],
            'password'   => ['required', Password::min(8)->numbers()->mixedCase()],
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('curp')) {
            $this->merge(['curp' => strtoupper($this->input('curp'))]);
        }
    }
}
