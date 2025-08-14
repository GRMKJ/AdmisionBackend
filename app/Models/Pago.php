<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';
    protected $primaryKey = 'id_pagos';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_aspirantes', 'id_configuracion', 'tipo_pago', 'metodo_pago',
        'fecha_pago', 'referencia', 'comprobante_pago',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
    ];

    // Relaciones
    public function aspirante()
    {
        return $this->belongsTo(Aspirante::class, 'id_aspirantes', 'id_aspirantes');
    }

    public function configuracion()
    {
        return $this->belongsTo(ConfiguracionPago::class, 'id_configuracion', 'id_configuracion');
    }
}
