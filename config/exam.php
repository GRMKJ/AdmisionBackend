<?php

return [
    'export_endpoint' => env('EXAM_EXPORT_ENDPOINT'),
    'results_endpoint' => env('EXAM_RESULTS_ENDPOINT'),
    'api_token' => env('EXAM_API_TOKEN'),
    'timeout' => (int) env('EXAM_API_TIMEOUT', 15),
];
