<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('aspirantes', function (Blueprint $table) {
            $table->string('folio_examen', 50)
                  ->nullable()
                  ->after('promedio_general'); // o después de donde tenga más sentido
        });
    }

    public function down(): void
    {
        Schema::table('aspirantes', function (Blueprint $table) {
            $table->dropColumn('folio_examen');
        });
    }
};
