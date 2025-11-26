<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // <- cambia
use Laravel\Sanctum\HasApiTokens;

class Aspirante extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'aspirantes';
    protected $primaryKey = 'id_aspirantes';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_carrera',
        'id_bachillerato',
        'nombre',
        'ap_paterno',
        'ap_materno',
        'telefono',
        'fecha_registro',
        'estatus',
        'curp',
        'password',
        'promedio_general',
        'folio_examen',
        'email',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'fecha_registro' => 'date',
        'estatus' => 'integer',
        'pago_validado' => 'boolean',
        'progress_step' => 'integer',
        'folio_exportado_at' => 'datetime',
        'resultado_notificado_at' => 'datetime',
    ];

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'id_carrera', 'id_carreras');
    }
    public function alumno()
    {
        return $this->hasOne(Alumno::class, 'id_aspirantes', 'id_aspirantes');
    }
    public function documentos()
    {
        return $this->hasMany(Documento::class, 'id_aspirantes', 'id_aspirantes');
    }
    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_aspirantes', 'id_aspirantes');
    }
    public function bachillerato()
    {
        return $this->belongsTo(Bachillerato::class, 'id_bachillerato', 'id_bachillerato');
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class, 'id_aspirantes', 'id_aspirantes');
    }
}
