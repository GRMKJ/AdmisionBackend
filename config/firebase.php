<?php
declare(strict_types=1);

return [
    'default' => env('FIREBASE_PROJECT', 'sii-admision-uth'),

    'projects' => [
        // ✅ clave debe coincidir con FIREBASE_PROJECT
        'sii-admision-uth' => [
            // puede ser string con la ruta del JSON
            'credentials' => env('FIREBASE_CREDENTIALS', storage_path('firebase/sa.json')),


            // opcional pero recomendado: fuerza el project_id
            'project_id' => env('FIREBASE_PROJECT_ID', 'sii-admision-uth'),

            'auth' => ['tenant_id' => env('FIREBASE_AUTH_TENANT_ID')],
            'database' => ['url' => env('FIREBASE_DATABASE_URL')],
            'dynamic_links' => ['default_domain' => env('FIREBASE_DYNAMIC_LINKS_DEFAULT_DOMAIN')],
            'storage' => ['default_bucket' => env('FIREBASE_STORAGE_DEFAULT_BUCKET')],
            'cache_store' => env('FIREBASE_CACHE_STORE', 'file'),
            'logging' => [
                'http_log_channel' => env('FIREBASE_HTTP_LOG_CHANNEL'),
                'http_debug_log_channel' => env('FIREBASE_HTTP_DEBUG_LOG_CHANNEL'),
            ],
            'http_client_options' => [
                'proxy' => env('FIREBASE_HTTP_CLIENT_PROXY'),
                'timeout' => env('FIREBASE_HTTP_CLIENT_TIMEOUT'),
                'guzzle_middlewares' => [],
                'verify' => 'C:\certs\cacert.pem',
            ],
        ],
    ],
];
