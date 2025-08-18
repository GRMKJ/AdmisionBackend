<?php

namespace Database\Seeders;

use App\Models\Carrera;
use Illuminate\Database\Seeder;

class CarrerasSeeder extends Seeder
{
    public function run(): void
{
    $carreras = [
        'Ingeniería en Desarrollo y Gestión de Software',
        'Ingeniería en Mecatrónica',
        'Ingeniería en Energías Renovables',
        'Ingeniería en Procesos y Operaciones Industriales',
        'Ingeniería en Logística Internacional',
        'Ingeniería en Nanotecnología',
        'Licenciatura en Innovación de Negocios y Mercadotecnia',
        'Licenciatura en Gestión del Capital Humano',
        'Licenciatura en Turismo',
        'Licenciatura en Terapia Física',
        'TSU en Tecnologías de la Información área Desarrollo de Software Multiplataforma',
        'TSU en Mecatrónica área Automatización',
        'TSU en Energías Renovables área Calidad y Ahorro de Energía',
        'TSU en Procesos Industriales área Manufactura',
        'TSU en Logística área Cadena de Suministro',
        'TSU en Nanotecnología área Materiales',
        'TSU en Innovación de Negocios y Mercadotecnia',
        'TSU en Administración área Capital Humano',
        'TSU en Turismo área Hotelería',
        'TSU en Terapia Física',
    ];

    foreach ($carreras as $carrera) {
        Carrera::create(['carrera' => $carrera]);
    }
}
}
