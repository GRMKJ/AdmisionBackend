<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bachillerato;

class BachilleratoSeeder extends Seeder
{
    public function run(): void
    {
        $bachilleratos = [
            ['nombre' => 'Colegio de Bachilleres del Estado de Puebla Plantel 1', 'municipio' => 'Puebla', 'estado' => 'Puebla'],
            ['nombre' => 'Preparatoria Benito Juárez', 'municipio' => 'Huejotzingo', 'estado' => 'Puebla'],
            ['nombre' => 'Colegio de Ciencias y Humanidades', 'municipio' => 'Atlixco', 'estado' => 'Puebla'],
        ];

        foreach ($bachilleratos as $bach) {
            Bachillerato::create($bach);
        }
    }
}
