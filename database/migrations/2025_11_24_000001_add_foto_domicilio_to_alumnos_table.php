<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->string('foto', 255)->nullable()->after('numero_seguro_social');
            $table->text('domicilio')->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->dropColumn(['foto', 'domicilio']);
        });
    }
};
