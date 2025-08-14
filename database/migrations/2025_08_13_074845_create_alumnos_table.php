<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id('id_inscripcion');

            // FK -> aspirantes(id_aspirantes)
            $table->unsignedBigInteger('id_aspirantes');
            $table->foreign('id_aspirantes')
                  ->references('id_aspirantes')->on('aspirantes')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete(); // al borrar aspirante, borrar inscripción

            $table->date('fecha_inscripcion')->nullable();
            $table->string('nombre_carrera', 200)->nullable(); // opcional si ya está en carreras
            $table->string('matricula', 50)->nullable();
            $table->date('fecha_inicio_clase')->nullable();
            $table->date('fecha_fin_clases')->nullable();
            $table->string('correo_instituto', 150)->nullable();
            $table->string('numero_seguro_social', 50)->nullable();
            $table->tinyInteger('estatus')->default(1);
            $table->timestamps();

            // Índices opcionales:
            // $table->unique('matricula');
            // $table->index('correo_instituto');
        });
    }

    public function down(): void {
        Schema::dropIfExists('alumnos');
    }
};
