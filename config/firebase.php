<?php
declare(strict_types=1);

use Illuminate\Support\Str;
use RuntimeException;

return [
    'default' => env('FIREBASE_PROJECT', 'sii-admision-uth'),

    'projects' => [
        // ✅ clave debe coincidir con FIREBASE_PROJECT
        'sii-admision-uth' => [
            // puede ser string con la ruta del JSON
            'credentials' => value(static function () {
                $inlineJson = env('FIREBASE_CREDENTIALS_JSON');
                if (is_string($inlineJson) && trim($inlineJson) !== '') {
                    return $inlineJson;
                }

                $base64 = env('FIREBASE_CREDENTIALS_BASE64');
                if (is_string($base64) && trim($base64) !== '') {
                    $decoded = base64_decode($base64, true);

                    if ($decoded === false) {
                        throw new RuntimeException('FIREBASE_CREDENTIALS_BASE64 no contiene un JSON válido en base64.');
                    }

                    return $decoded;
                }

                $path = env('FIREBASE_CREDENTIALS');
                if (!is_string($path) || trim($path) === '') {
                    return storage_path('firebase/sa.json');
                }

                $path = trim($path);

                if (str_starts_with($path, '{')) {
                    return $path;
                }

                $isAbsolute = Str::startsWith($path, ['/', '\\'])
                    || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

                if ($isAbsolute) {
                    return $path;
                }

                return base_path($path);
            }),


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
