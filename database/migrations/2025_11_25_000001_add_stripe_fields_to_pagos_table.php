<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->decimal('monto_pagado', 10, 2)->nullable()->after('metodo_pago');
            $table->string('stripe_session_id')->nullable()->after('monto_pagado');
            $table->string('stripe_payment_intent')->nullable()->after('stripe_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn(['monto_pagado', 'stripe_session_id', 'stripe_payment_intent']);
        });
    }
};
