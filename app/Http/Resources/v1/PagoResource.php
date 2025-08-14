<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id_pagos,
            'tipo'         => $this->tipo_pago,
            'metodo'       => $this->metodo_pago,
            'fechaPago'    => optional($this->fecha_pago)->toDateString(),
            'referencia'   => $this->referencia,
            'comprobante'  => $this->comprobante_pago,     // path relativo
            'comprobanteUrl' => $this->comprobante_url,    // URL pública
            'monto'        => optional($this->configuracion)->monto ? (float) $this->configuracion->monto : null,
            'aspirante'    => [
                'id'     => $this->aspirante?->id_aspirantes,
                'nombre' => $this->aspirante?->nombre,
            ],
            'configuracion' => [
                'id'       => $this->configuracion?->id_configuracion,
                'concepto' => $this->configuracion?->concepto,
            ],
            'links' => [
                'self' => route('pagos.show', ['pago' => $this->id_pagos]),
            ],
        ];
    }
}
