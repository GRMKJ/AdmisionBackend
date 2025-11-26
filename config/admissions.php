<?php

return [
    // Configuración del ID de pago para el examen de diagnóstico
    'diagnostic_payment_config_id' => (int) env('DIAGNOSTIC_PAYMENT_CONFIG_ID', 3),
    'seguro_payment_config_id' => (int) env('SEGURO_PAYMENT_CONFIG_ID', 4),
];
