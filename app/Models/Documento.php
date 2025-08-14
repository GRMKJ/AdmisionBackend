<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    use HasFactory;

    protected $table = 'documentos';
    protected $primaryKey = 'id_documentos';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_aspirantes', 'pendientes', 'archivo_pat', 'fecha_registro',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
    ];

    // Relaciones
    public function aspirante()
    {
        return $this->belongsTo(Aspirante::class, 'id_aspirantes', 'id_aspirantes');
    }
}
