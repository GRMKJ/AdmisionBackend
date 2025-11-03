<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign('pagos_id_admin_validador_foreign'); // nombre puede variar
            $table->foreign('id_admin_validador')
                ->references('id_administrativo')
                ->on('administrativos')
                ->nullOnDelete();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign(['id_admin_validador']);
            $table->foreign('id_admin_validador')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
