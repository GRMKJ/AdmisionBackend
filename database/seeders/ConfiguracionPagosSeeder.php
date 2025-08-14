<?php

namespace Database\Seeders;

use App\Models\ConfiguracionPago;
use Illuminate\Database\Seeder;

class ConfiguracionPagosSeeder extends Seeder
{
    public function run(): void
    {
        ConfiguracionPago::insert([
            [
                'concepto' => 'Inscripción',
                'monto' => 1500.00,
                'vigencia_inicio' => now()->startOfYear(),
                'vigencia_fin' => now()->endOfYear(),
                'cuenta_bancaria' => '1234567890',
                'clabe_interbancaria' => '002010012345678901',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'concepto' => 'Colegiatura Mensual',
                'monto' => 2200.00,
                'vigencia_inicio' => now()->startOfYear(),
                'vigencia_fin' => now()->endOfYear(),
                'cuenta_bancaria' => '1234567890',
                'clabe_interbancaria' => '002010012345678901',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }
}
