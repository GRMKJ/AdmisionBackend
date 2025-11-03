<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('administrativos', function (Blueprint $table) {
            $table->id('id_administrativo');
            $table->string('numero_empleado', 50)->unique();
            $table->string('nombre', 150);
            $table->string('ap_paterno', 150)->nullable();
            $table->string('ap_materno', 150)->nullable();
            $table->string('password');
            $table->tinyInteger('estatus')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('administrativos');
    }
};
