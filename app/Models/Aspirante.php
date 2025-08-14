<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aspirante extends Model
{
    use HasFactory;

    protected $table = 'aspirantes';
    protected $primaryKey = 'id_aspirantes';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_carrera', 'nombre', 'ap_paterno', 'ap_materno',
        'telefono', 'fecha_registro', 'estatus',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
        'estatus' => 'integer',
    ];

    // Relaciones
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
}
