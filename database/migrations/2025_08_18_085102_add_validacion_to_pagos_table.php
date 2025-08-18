<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            // estado_validacion: 0 = pendiente, 1 = validado, 2 = rechazado (ejemplo)
            $table->tinyInteger('estado_validacion')
                  ->default(0)
                  ->after('referencia');

            // admin que validó (nullable porque inicialmente nadie lo valida)
            $table->unsignedBigInteger('id_admin_validador')
                  ->nullable()
                  ->after('estado_validacion');

            // ⚠️ si ya tienes tabla administradores/usuarios, agrega la relación
            $table->foreign('id_admin_validador')
                  ->references('id') // ajusta según tu tabla admins
                  ->on('users')      // si validan usuarios, o 'administradores'
                  ->nullOnDelete();  // si se borra el admin, queda NULL
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign(['id_admin_validador']);
            $table->dropColumn(['estado_validacion', 'id_admin_validador']);
        });
    }
};
