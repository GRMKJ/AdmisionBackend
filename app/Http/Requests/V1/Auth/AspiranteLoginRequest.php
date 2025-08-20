<?php

namespace App\Http\Requests\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AspiranteLoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'curp'     => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('curp')) {
            $this->merge(['curp' => strtoupper($this->input('curp'))]);
        }
    }
}
