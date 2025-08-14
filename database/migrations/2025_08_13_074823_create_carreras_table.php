<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('carreras', function (Blueprint $table) {
            $table->id('id_carreras');
            $table->string('carrera', 200);
            $table->string('duracion', 100)->nullable();
            $table->text('descripcion')->nullable();
            $table->tinyInteger('estatus')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('carreras');
    }
};
