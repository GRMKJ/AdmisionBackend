<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('documento_revisiones', function (Blueprint $table) {
            $table->id('id_revision');

            // Documento
            $table->unsignedBigInteger('id_documentos');
            $table->foreign('id_documentos')
                  ->references('id_documentos')->on('documentos')
                  ->onUpdate('cascade')
                  ->onDelete('cascade'); // si borras el doc, vuela su historial

            // Validador (puede ser null si el sistema resetea automáticamente)
            $table->unsignedBigInteger('id_validador')->nullable();
            $table->foreign('id_validador')
                  ->references('id_administrativo')->on('administrativos')
                  ->onUpdate('cascade')
                  ->onDelete('no action');

            // 0=pending (reset), 1=aprobado, 2=rechazado
            $table->tinyInteger('estado')->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_evento')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('documento_revisiones');
    }
};
