<?php

namespace Database\Seeders;

use App\Models\Aspirante;
use App\Models\Documento;
use Illuminate\Database\Seeder;

class DocumentosSeeder extends Seeder
{
    public function run(): void
    {
        $aspirantes = Aspirante::all();

        foreach ($aspirantes as $asp) {
            Documento::create([
                'id_aspirantes' => $asp->id_aspirantes,
                'pendientes' => 'Acta de nacimiento; CURP',
                'archivo_pat' => 'docs/'.$asp->id_aspirantes.'/curp.pdf',
                'fecha_registro' => now(),
            ]);
        }
    }
}
