<?php

namespace Database\Seeders;

use App\Models\Aspirante;
use App\Models\Carrera;
use Illuminate\Database\Seeder;

class AspirantesSeeder extends Seeder
{
    public function run(): void
    {
        $soft = Carrera::where('carrera', 'TSU en Logística área Cadena de Suministro')->first();
        $adm  = Carrera::where('carrera', 'TSU en Energías Renovables área Calidad y Ahorro de Energía')->first();

        Aspirante::insert([
    [
        'id_carrera'     => $soft->id_carreras,
        'nombre'         => 'Juan',
        'ap_paterno'     => 'Pérez',
        'ap_materno'     => 'López',
        'telefono'       => '555-111-2222',
        'curp'           => 'PEPJ010101HDFLRN01', // CURP ficticia
        'password'       => bcrypt('12345678'),   // para cumplir con fillable
        'fecha_registro' => now(),
        'estatus'        => 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ],
    [
        'id_carrera'     => $adm->id_carreras,
        'nombre'         => 'María',
        'ap_paterno'     => 'García',
        'ap_materno'     => 'Hernández',
        'telefono'       => '555-333-4444',
        'curp'           => 'GAHM010101MDFLRN02', // CURP ficticia distinta
        'password'       => bcrypt('12345678'),
        'fecha_registro' => now(),
        'estatus'        => 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ],
]);

    }
}
