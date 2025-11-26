<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('administrativos', function (Blueprint $table) {
            // Añadimos correo electrónico como nullable. No marcamos unique por defecto
            // por seguridad (puede fallar si ya existen duplicados). Si quieres
            // unique, añade ->unique() aquí.
            $table->string('email')->nullable()->after('numero_empleado');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('administrativos', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
