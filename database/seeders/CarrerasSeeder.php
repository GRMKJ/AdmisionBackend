<?php

namespace Database\Seeders;

use App\Models\Carrera;
use Illuminate\Database\Seeder;

class CarrerasSeeder extends Seeder
{
    public function run(): void
    {
        Carrera::insert([
            [
                'carrera' => 'Ingeniería en Software',
                'duracion' => '9 semestres',
                'descripcion' => 'Plan orientado a desarrollo de software.',
                'estatus' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'carrera' => 'Administración',
                'duracion' => '8 semestres',
                'descripcion' => 'Gestión empresarial.',
                'estatus' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }
}
