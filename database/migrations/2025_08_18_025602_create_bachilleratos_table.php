<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bachilleratos', function (Blueprint $table) {
            $table->id('id_bachillerato');
            $table->string('nombre', 255);
            $table->string('municipio', 150);
            $table->string('estado', 150);
            $table->timestamps();
        });

        // Relación con aspirantes
        Schema::table('aspirantes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_bachillerato')->nullable()->after('curp');
            $table->foreign('id_bachillerato')
                  ->references('id_bachillerato')->on('bachilleratos')
                  ->nullOnDelete(); // si se elimina el bachillerato, aspirante queda con null
        });
    }

    public function down(): void {
        Schema::table('aspirantes', function (Blueprint $table) {
            $table->dropForeign(['id_bachillerato']);
            $table->dropColumn('id_bachillerato');
        });

        Schema::dropIfExists('bachilleratos');
    }
};
