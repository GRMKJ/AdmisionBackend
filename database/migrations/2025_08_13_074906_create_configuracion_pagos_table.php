<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('configuracion_pagos', function (Blueprint $table) {
            $table->id('id_configuracion');
            $table->string('concepto', 200);
            $table->decimal('monto', 10, 2);
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fin')->nullable();
            $table->string('cuenta_bancaria', 32)->nullable();
            $table->string('clabe_interbancaria', 18)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('configuracion_pagos');
    }
};
