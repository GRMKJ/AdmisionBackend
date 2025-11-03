<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesFileUrls; // <-
use Illuminate\Support\Facades\Storage;

class Documento extends Model
{
    use HasFactory, GeneratesFileUrls; // <-

    protected $table = 'documentos';
    protected $primaryKey = 'id_documentos';

    protected $fillable = [
        'id_aspirantes','nombre','pendientes','archivo_pat','fecha_registro',
        'estado_validacion','observaciones','fecha_validacion','id_validador',
    ];

    protected $casts = [
        'fecha_registro'    => 'date',
        'fecha_validacion'  => 'datetime',
        'estado_validacion' => 'integer',
    ];

    protected $appends = ['archivo_url'];

    public function getArchivoUrlAttribute(): ?string
    {
        // usa el helper robusto
        return $this->diskUrl('public', $this->archivo_pat);
    }

    public function aspirante()
    {
        return $this->belongsTo(Aspirante::class, 'id_aspirantes', 'id_aspirantes');
    }

    public function validador()
    {
        return $this->belongsTo(Administrativo::class, 'id_validador', 'id_administrativo');
    }

    public function revisiones()
    {
        return $this->hasMany(DocumentoRevision::class, 'id_documentos', 'id_documentos')->latest('fecha_evento');
    }
}
