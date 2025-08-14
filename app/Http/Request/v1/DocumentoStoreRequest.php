<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class DocumentoStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_aspirantes'  => ['required','exists:aspirantes,id_aspirantes'],
            'pendientes'     => ['nullable','string'],
            // archivo subido (opcional al crear)
            'archivo'        => ['nullable','file','max:5120','mimes:pdf,jpg,jpeg,png,doc,docx'],
            'fecha_registro' => ['nullable','date'],
        ];
    }
}
