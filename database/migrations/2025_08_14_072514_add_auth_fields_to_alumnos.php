<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('alumnos', function (Blueprint $table) {
            // si ya existe y es nullable, primero asegúrate de llenarla antes de hacerla única
            $table->string('matricula', 50)->nullable(false)->change();
            $table->string('password')->after('matricula')->nullable();
            $table->unique('matricula');
        });
    }
    public function down(): void {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->dropUnique(['matricula']);
            $table->dropColumn('password');
            // si necesitas revertir el change() a nullable, hazlo aquí según tu esquema original
        });
    }
};
