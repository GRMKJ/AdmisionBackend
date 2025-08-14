<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id('id_documentos');

            // FK -> aspirantes(id_aspirantes)
            $table->unsignedBigInteger('id_aspirantes');
            $table->foreign('id_aspirantes')
                  ->references('id_aspirantes')->on('aspirantes')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            $table->text('pendientes')->nullable();     // o JSON si prefieres estructura
            $table->string('archivo_pat', 255)->nullable(); // path/filename
            $table->date('fecha_registro')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('documentos');
    }
};
