<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // <- cambia
use Laravel\Sanctum\HasApiTokens;

class Alumno extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'alumnos';
    protected $primaryKey = 'id_inscripcion';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_aspirantes','fecha_inscripcion','nombre_carrera','matricula',
        'fecha_inicio_clase','fecha_fin_clases','correo_instituto',
        'numero_seguro_social','estatus','password',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'fecha_inscripcion' => 'date',
        'fecha_inicio_clase' => 'date',
        'fecha_fin_clases' => 'date',
        'estatus' => 'integer',
    ];

    public function aspirante(){ return $this->belongsTo(Aspirante::class, 'id_aspirantes', 'id_aspirantes'); }
}
