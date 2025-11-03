<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class DocumentoReviewRequest extends FormRequest
{
    public function authorize(): bool { return true; } // protegemos por middleware/abilities

    public function rules(): array
    {
        return [
            // 1 = aprobado, 2 = rechazado (0 no tiene sentido en revisión)
            'estado'        => ['required','integer','in:1,2'],
            'observaciones' => ['nullable','string'],
        ];
    }
}
