<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    use HasFactory;

    protected $table = 'carreras';
    protected $primaryKey = 'id_carreras';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'carrera', 'duracion', 'descripcion', 'estatus',
    ];

    protected $casts = [
        'estatus' => 'integer',
    ];

    // Relaciones
    public function aspirantes()
    {
        return $this->hasMany(Aspirante::class, 'id_carrera', 'id_carreras');
    }
}
