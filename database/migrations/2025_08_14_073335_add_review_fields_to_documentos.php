<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('documentos', function (Blueprint $table) {
            // 0 = pendiente, 1 = aprobado, 2 = rechazado
            $table->tinyInteger('estado_validacion')->default(0)->after('fecha_registro');
            $table->text('observaciones')->nullable()->after('estado_validacion');
            $table->dateTime('fecha_validacion')->nullable()->after('observaciones');

            // Validador: administrativos(id_administrativo)
            $table->unsignedBigInteger('id_validador')->nullable()->after('fecha_validacion');
            $table->foreign('id_validador')
                  ->references('id_administrativo')->on('administrativos')
                  ->onUpdate('cascade')
                  ->onDelete('no action'); // SQL Server no acepta 'restrict'
        });
    }

    public function down(): void {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign(['id_validador']);
            $table->dropColumn(['estado_validacion','observaciones','fecha_validacion','id_validador']);
        });
    }
};
