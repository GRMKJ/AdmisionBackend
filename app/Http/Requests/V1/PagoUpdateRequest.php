<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class PagoUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_aspirantes'    => ['sometimes','exists:aspirantes,id_aspirantes'],
            'id_configuracion' => ['sometimes','exists:configuracion_pagos,id_configuracion'],
            'tipo_pago'        => ['nullable','string','max:50'],
            'metodo_pago'      => ['nullable','string','max:50'],
            'fecha_pago'       => ['nullable','date'],
            'referencia'       => ['nullable','string','max:120'],
            // archivo comprobante (opcional al actualizar)
            'comprobante'      => ['nullable','file','max:5120','mimes:pdf,jpg,jpeg,png'],
            // borrar comprobante sin subir otro
            'delete_comprobante' => ['nullable','boolean'],
        ];
    }
}
