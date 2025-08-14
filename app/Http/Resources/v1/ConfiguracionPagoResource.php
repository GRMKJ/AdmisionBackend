<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfiguracionPagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id_configuracion,
            'concepto'       => $this->concepto,
            'monto'          => (float) $this->monto,
            'vigenciaInicio' => optional($this->vigencia_inicio)->toDateString(),
            'vigenciaFin'    => optional($this->vigencia_fin)->toDateString(),
            'cuentaBancaria' => $this->cuenta_bancaria,
            'clabe'          => $this->clabe_interbancaria,
            'links' => [
                'self' => route('configuracion-pagos.show', ['configuracion_pago' => $this->id_configuracion]),
            ],
        ];
    }
}
