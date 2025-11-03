<?php

namespace Database\Seeders;

use App\Models\Aspirante;
use App\Models\ConfiguracionPago;
use App\Models\Pago;
use Illuminate\Database\Seeder;

class PagosSeeder extends Seeder
{
    public function run(): void
    {
        $inscripcion = ConfiguracionPago::where('concepto', 'Inscripción')->first();

        Aspirante::all()->each(function ($asp) use ($inscripcion) {
            Pago::create([
                'id_aspirantes' => $asp->id_aspirantes,
                'id_configuracion' => $inscripcion->id_configuracion,
                'tipo_pago' => 'Inscripción',
                'metodo_pago' => 'Transferencia',
                'fecha_pago' => now()->toDateString(),
                'referencia' => 'REF-' . str_pad($asp->id_aspirantes, 4, '0', STR_PAD_LEFT),
                'comprobante_pago' => 'comprobantes/'.$asp->id_aspirantes.'/inscripcion.pdf',
            ]);
        });
    }
}
