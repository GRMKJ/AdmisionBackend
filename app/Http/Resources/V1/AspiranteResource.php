<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AspiranteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Mapea step -> ruta recomendada (para el frontend)
        $progressStep = (int) ($this->progress_step ?? $this->step ?? 1);
        $redirectTo = match ($progressStep) {
            1 => '/admision',
            2 => '/admision/pagoexamen',
            3 => '/admision/pagoexamen/status',
            4 => '/admision/documentos/subida',
            5 => '/admision/documentos/estado',
            6 => '/alumno/inicio',
            default => '/admision'
        };

        return [
            'id'             => $this->id_aspirantes,
            'curp'           => $this->curp,
            'nombre'         => $this->nombre,
            'ap_paterno'     => $this->ap_paterno,
            'ap_materno'     => $this->ap_materno,
            'telefono'       => $this->telefono,
            'email'          => $this->email,
            'folio_examen'   => $this->folio_examen,
            'promedio_general' => $this->promedio_general,
            'estatus'        => (int) $this->estatus,
            'fecha_registro' => optional($this->fecha_registro)->toISOString(),
            'progress_step'  => $progressStep,
            'payment_status'   => (int) ($this->payment_status ?? 0),
            'documents_status' => (int) ($this->documents_status ?? 0),
            'redirect_to'    => $redirectTo,
            'carrera'        => $this->whenLoaded('carrera', function ($carrera) {
                return [
                    'id'         => $carrera->id_carreras,
                    'carrera'    => $carrera->carrera,
                    'duracion'   => $carrera->duracion,
                    'descripcion'=> $carrera->descripcion,
                ];
            }),
            'bachillerato'   => $this->whenLoaded('bachillerato', function ($bachillerato) {
                return [
                    'id'        => $bachillerato->id_bachillerato,
                    'nombre'    => $bachillerato->nombre,
                    'municipio' => $bachillerato->municipio,
                    'estado'    => $bachillerato->estado,
                ];
            }),
            'documentos'     => $this->whenLoaded('documentos', function ($documentos) {
                return $documentos->map(function ($doc) {
                    $estado = match ((int) $doc->estado_validacion) {
                        1 => 'Validado',
                        2 => 'Rechazado',
                        default => 'Pendiente',
                    };

                    return [
                        'id' => $doc->id_documentos,
                        'nombre' => $doc->nombre,
                        'estado_validacion' => (int) $doc->estado_validacion,
                        'estado_validacion_texto' => $estado,
                        'archivo_url' => $doc->archivo_url,
                        'observaciones' => $doc->observaciones,
                        'fecha_registro' => optional($doc->fecha_registro)->toDateString(),
                        'fecha_validacion' => optional($doc->fecha_validacion)->toDateTimeString(),
                        'validador' => $doc->validador?->only(['id_administrativo', 'nombre', 'ap_paterno', 'ap_materno']),
                    ];
                });
            }),
            'pagos' => $this->whenLoaded('pagos', function ($pagos) {
                return $pagos->map(function ($pago) {
                    return [
                        'id' => $pago->id_pagos,
                        'tipo_pago' => $pago->tipo_pago,
                        'metodo_pago' => $pago->metodo_pago,
                        'referencia' => $pago->referencia,
                        'fecha_pago' => optional($pago->fecha_pago)->toDateString(),
                        'estado_validacion' => (int) $pago->estado_validacion,
                        'estado_validacion_texto' => $pago->estado_validacion_texto,
                        'comprobante_url' => $pago->comprobante_url,
                        'configuracion' => $pago->configuracion?->only(['id_configuracion', 'concepto', 'monto']),
                    ];
                });
            }),
        ];
    }
}
