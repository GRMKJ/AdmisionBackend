<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class PagoStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_aspirantes'    => ['required','exists:aspirantes,id_aspirantes'],
            'id_configuracion' => ['required','exists:configuracion_pagos,id_configuracion'],
            'tipo_pago'        => ['nullable','string','max:50'],
            'metodo_pago'      => ['nullable','string','max:50'],
            'fecha_pago'       => ['nullable','date'],
            'referencia'       => ['nullable','string','max:120'],
            // archivo comprobante (opcional al crear)
            'comprobante'      => ['nullable','file','max:5120','mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
