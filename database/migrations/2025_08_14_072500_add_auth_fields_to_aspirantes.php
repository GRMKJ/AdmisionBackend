<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('aspirantes', function (Blueprint $table) {
            $table->string('curp', 18)->after('telefono')->nullable()->unique();
            $table->string('password')->after('curp')->nullable();
        });
    }
    public function down(): void {
        Schema::table('aspirantes', function (Blueprint $table) {
            $table->dropUnique(['curp']);
            $table->dropColumn(['curp','password']);
        });
    }
};
