<?php

namespace Database\Seeders;

use App\Models\Aspirante;
use App\Models\Carrera;
use Illuminate\Database\Seeder;

class AspirantesSeeder extends Seeder
{
    public function run(): void
    {
        $soft = Carrera::where('carrera', 'Ingeniería en Software')->first();
        $adm  = Carrera::where('carrera', 'Administración')->first();

        Aspirante::insert([
            [
                'id_carrera' => $soft->id_carreras,
                'nombre' => 'Juan',
                'ap_paterno' => 'Pérez',
                'ap_materno' => 'López',
                'telefono' => '555-111-2222',
                'fecha_registro' => now(),
                'estatus' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id_carrera' => $adm->id_carreras,
                'nombre' => 'María',
                'ap_paterno' => 'García',
                'ap_materno' => 'Hernández',
                'telefono' => '555-333-4444',
                'fecha_registro' => now(),
                'estatus' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }
}
