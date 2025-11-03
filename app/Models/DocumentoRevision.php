<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoRevision extends Model
{
    protected $table = 'documento_revisiones';
    protected $primaryKey = 'id_revision';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_documentos', 'id_validador', 'estado', 'observaciones', 'fecha_evento'
    ];

    protected $casts = [
        'estado' => 'integer',
        'fecha_evento' => 'datetime',
    ];

    public function documento()
    {
        return $this->belongsTo(Documento::class, 'id_documentos', 'id_documentos');
    }

    public function validador()
    {
        return $this->belongsTo(Administrativo::class, 'id_validador', 'id_administrativo');
    }
}
