<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class DocumentoReviewRequest extends FormRequest
{
    public function authorize(): bool { return true; } // protegemos por middleware/abilities

    public function rules(): array
    {
        return [
            // 1 = pendiente manual, 2 = validación automática / rechazo, 3 = validación manual
            'estado'        => ['required','integer','in:1,2,3'],
            'observaciones' => ['nullable','string'],
        ];
    }
}
