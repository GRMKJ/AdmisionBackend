<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Administrativo extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'administrativos';
    protected $primaryKey = 'id_administrativo';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'numero_empleado','nombre','ap_paterno','ap_materno','password','estatus',
    ];

    protected $hidden = ['password'];

    public function revisionesRealizadas()
{
    return $this->hasMany(DocumentoRevision::class, 'id_validador', 'id_administrativo')->latest('fecha_evento');
}
}
