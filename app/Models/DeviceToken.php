<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class DeviceToken extends Model
{
    protected $fillable = ['id_aspirantes','fcm_token','platform'];
    public function aspirante() {
        return $this->belongsTo(Aspirante::class, 'id_aspirantes', 'id_aspirantes');
    }
}
