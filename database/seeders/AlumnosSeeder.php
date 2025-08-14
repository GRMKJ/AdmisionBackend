<?php

namespace Database\Seeders;

use App\Models\Alumno;
use App\Models\Aspirante;
use Illuminate\Database\Seeder;

class AlumnosSeeder extends Seeder
{
    public function run(): void
    {
        $asp1 = Aspirante::first();
        $asp2 = Aspirante::skip(1)->first();

        Alumno::insert([
            [
                'id_aspirantes' => $asp1->id_aspirantes,
                'fecha_inscripcion' => now()->toDateString(),
                'nombre_carrera' => $asp1->carrera->carrera ?? null,
                'matricula' => 'A001',
                'fecha_inicio_clase' => now()->toDateString(),
                'fecha_fin_clases' => now()->addMonths(4)->toDateString(),
                'correo_instituto' => 'a001@instituto.edu',
                'numero_seguro_social' => 'NSS-001',
                'estatus' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id_aspirantes' => $asp2->id_aspirantes,
                'fecha_inscripcion' => now()->toDateString(),
                'nombre_carrera' => $asp2->carrera->carrera ?? null,
                'matricula' => 'A002',
                'fecha_inicio_clase' => now()->toDateString(),
                'fecha_fin_clases' => now()->addMonths(4)->toDateString(),
                'correo_instituto' => 'a002@instituto.edu',
                'numero_seguro_social' => 'NSS-002',
                'estatus' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }
}
