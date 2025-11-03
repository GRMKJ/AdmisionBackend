<?php

namespace Database\Seeders;

use App\Models\Administrativo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdministrativoSeeder extends Seeder
{
    public function run(): void
    {
        Administrativo::insert([
            [
                'numero_empleado' => 'ADM001',
                'nombre'          => 'Juan',
                'ap_paterno'      => 'Pérez',
                'ap_materno'      => 'López',
                'password'        => Hash::make('admin'), // 🔑 contraseña encriptada
                'estatus'         => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'numero_empleado' => 'ADM002',
                'nombre'          => 'María',
                'ap_paterno'      => 'García',
                'ap_materno'      => 'Ramírez',
                'password'        => Hash::make('admin123'),
                'estatus'         => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);
    }
}
