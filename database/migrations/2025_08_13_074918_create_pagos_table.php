<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id('id_pagos');

            // FK -> aspirantes(id_aspirantes)
            $table->unsignedBigInteger('id_aspirantes');
            $table->foreign('id_aspirantes')
                  ->references('id_aspirantes')->on('aspirantes')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // FK -> configuracion_pagos(id_configuracion)
            $table->unsignedBigInteger('id_configuracion');
            $table->foreign('id_configuracion')
                  ->references('id_configuracion')->on('configuracion_pagos')
                  ->cascadeOnUpdate()
                  ->noActionOnDelete();

            $table->string('tipo_pago', 50)->nullable();   // ej: inscripción, colegiatura, etc.
            $table->string('metodo_pago', 50)->nullable(); // efectivo, transferencia, tarjeta...
            $table->date('fecha_pago')->nullable();
            $table->string('referencia', 120)->nullable();
            $table->string('comprobante_pago', 255)->nullable(); // path del archivo
            $table->timestamps();

            // Índices útiles:
            // $table->index(['id_aspirantes', 'id_configuracion']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('pagos');
    }
};
