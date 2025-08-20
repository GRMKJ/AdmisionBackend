<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PagoDetalleResource extends JsonResource
{
    // $this->resource debe ser un array con keys: aspirante, pago
    public function toArray($request)
    {
        $asp = $this['aspirante'];
        $pago = $this['pago'];

        return [
            'aspirante' => [
                'id_aspirantes' => $asp->id_aspirantes,
                'nombre'        => $asp->nombre,
                'ap_paterno'    => $asp->ap_paterno,
                'ap_materno'    => $asp->ap_materno,
                'folio_examen'  => $asp->folio_examen,
                'carrera'       => [
                    'carrera' => data_get($asp, 'carrera.carrera', null), // si tienes relación
                ],
            ],
            'pago' => [
                'id_pagos'           => $pago->id_pagos,
                'referencia'         => $pago->referencia,
                'estado_validacion'  => $pago->estado_validacion,
                'estado_texto'       => $pago->estado_validacion_texto, // ← útil para Flutter
                'metodo_pago'        => $pago->metodo_pago,
                'fecha_pago'         => optional($pago->fecha_pago)?->toIso8601String(),
                'id_admin_validador' => $pago->id_admin_validador,
                'updated_at'         => optional($pago->updated_at)?->toIso8601String(),
            ],
        ];
    }
}
