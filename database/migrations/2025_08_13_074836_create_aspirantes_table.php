<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('aspirantes', function (Blueprint $table) {
            $table->id('id_aspirantes');

            // FK -> carreras(id_carreras)
            $table->unsignedBigInteger('id_carrera')->nullable();
            $table->foreign('id_carrera')
                  ->references('id_carreras')->on('carreras')
                  ->cascadeOnUpdate()
                  ->noActionOnDelete(); // evita borrar carrera con aspirantes

            $table->string('nombre', 150);
            $table->string('ap_paterno', 150);
            $table->string('ap_materno', 150)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->date('fecha_registro')->nullable();
            $table->tinyInteger('estatus')->default(1);
            $table->decimal('promedio_general', 3, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('aspirantes');
    }
};
