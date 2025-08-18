<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // 1. Crear la tabla
        Schema::create('documento_revisiones', function (Blueprint $table) {
            $table->id('id_revision');

            $table->unsignedBigInteger('id_documentos');
            $table->unsignedBigInteger('id_validador')->nullable();

            $table->tinyInteger('estado')->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_evento')->useCurrent();

            $table->timestamps();

            $table->index('id_documentos');
            $table->index('id_validador');
        });

        // 2. Crear las FKs ya con nombres explícitos
        Schema::table('documento_revisiones', function (Blueprint $table) {
            // Documento -> Revisiones (CASCADE)
            $table->foreign('id_documentos', 'fk_rev_documento')
                ->references('id_documentos')->on('documentos')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Validador -> Revisiones (NO ACTION para evitar multiple cascade paths)
            $table->foreign('id_validador', 'fk_rev_validador')
                ->references('id_administrativo')->on('administrativos');
                // por omisión = NO ACTION en update/delete
                // si prefieres set null: ->nullOnDelete();
        });

        // 3. Ajustar FK en documentos (solo si quieres quitar cascada ahí)
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign(['id_validador']); // usa array con nombre de columna

            $table->foreign('id_validador', 'fk_doc_validador')
                ->references('id_administrativo')->on('administrativos');
                // sin cascada para evitar conflicto
        });
    }

    public function down(): void {
        Schema::table('documento_revisiones', function (Blueprint $table) {
            $table->dropForeign('fk_rev_documento');
            $table->dropForeign('fk_rev_validador');
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign('fk_doc_validador');
        });

        Schema::dropIfExists('documento_revisiones');
    }
};
