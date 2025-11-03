<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionPago extends Model
{
    use HasFactory;

    protected $table = 'configuracion_pagos';
    protected $primaryKey = 'id_configuracion';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'concepto', 'monto', 'vigencia_inicio', 'vigencia_fin',
        'cuenta_bancaria', 'clabe_interbancaria',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'vigencia_inicio' => 'date',
        'vigencia_fin' => 'date',
    ];

    // Relaciones
    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_configuracion', 'id_configuracion');
    }
}
