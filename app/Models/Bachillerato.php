<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bachillerato extends Model
{
    use HasFactory;

    protected $table = 'bachilleratos';
    protected $primaryKey = 'id_bachillerato';

  protected $fillable = [
    'nombre',
    'municipio',
    'estado',
];

    // Relación inversa con Aspirantes
    public function aspirantes()
    {
        return $this->hasMany(Aspirante::class, 'id_bachillerato', 'id_bachillerato');
    }
}
