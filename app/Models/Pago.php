<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesFileUrls; // <-
use Illuminate\Support\Facades\Storage;

class Pago extends Model
{
    use HasFactory, GeneratesFileUrls; // <-

    protected $table = 'pagos';
    protected $primaryKey = 'id_pagos';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_aspirantes',
        'id_configuracion',
        'tipo_pago',
        'metodo_pago',
        'monto_pagado',
        'stripe_session_id',
        'stripe_payment_intent',
        'fecha_pago',
        'referencia',
        'estado_validacion',
        'id_admin_validador',
        'receipt_sent_at',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'monto_pagado' => 'decimal:2',
        'receipt_sent_at' => 'datetime',
    ];
    public const EST_PENDIENTE = 0;
    public const EST_VALIDADO  = 1;
    public const EST_INVALIDO  = 2;


    protected $appends = ['comprobante_url'];

    public function aspirante() { return $this->belongsTo(Aspirante::class, 'id_aspirantes', 'id_aspirantes'); }
    public function configuracion() { return $this->belongsTo(ConfiguracionPago::class, 'id_configuracion', 'id_configuracion'); }

    public function getComprobanteUrlAttribute(): ?string
    {
        return $this->diskUrl('public', $this->comprobante_pago);
    }

    public function validador()
    {
        return $this->belongsTo(Administrativo::class, 'id_admin_validador', 'id_administrativo');
    }

    public function getEstadoValidacionTextoAttribute(): string
    {
        return match ($this->estado_validacion) {
            self::EST_VALIDADO  => 'Validado',
            self::EST_INVALIDO  => 'Referencia Inválida',
            default             => 'Pendiente de Validación',
        };
    }

}
