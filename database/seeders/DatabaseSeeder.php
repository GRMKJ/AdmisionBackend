<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CarrerasSeeder::class,
            ConfiguracionPagosSeeder::class,
            AspirantesSeeder::class,
            AlumnosSeeder::class,
            DocumentosSeeder::class,
            PagosSeeder::class,
            BachilleratoSeeder::class,
        ]);
    }
}
