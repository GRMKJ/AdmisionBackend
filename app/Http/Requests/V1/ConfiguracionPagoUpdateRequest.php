<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class ConfiguracionPagoUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'concepto'            => ['sometimes','string','max:200'],
            'monto'               => ['sometimes','numeric','min:0'],
            'vigencia_inicio'     => ['nullable','date'],
            'vigencia_fin'        => ['nullable','date','after_or_equal:vigencia_inicio'],
            'cuenta_bancaria'     => ['nullable','string','max:32'],
            'clabe_interbancaria' => ['nullable','string','size:18'],
        ];
    }
}
