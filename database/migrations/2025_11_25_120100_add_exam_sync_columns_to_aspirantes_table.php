<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('aspirantes', function (Blueprint $table) {
            $table->timestamp('folio_exportado_at')->nullable()->after('folio_examen');
            $table->string('resultado_examen', 20)->nullable()->after('folio_exportado_at');
            $table->timestamp('resultado_notificado_at')->nullable()->after('resultado_examen');
        });
    }

    public function down(): void
    {
        Schema::table('aspirantes', function (Blueprint $table) {
            $table->dropColumn(['folio_exportado_at', 'resultado_examen', 'resultado_notificado_at']);
        });
    }
};
