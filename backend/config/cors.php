<?php

$defaultOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
];

$configuredOrigins = env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL'));

$allowedOrigins = $configuredOrigins
    ? array_filter(array_map('trim', explode(',', $configuredOrigins)))
    : $defaultOrigins;

$localOriginPatterns = [
    '#^https?://localhost(?::\d+)?$#',
    '#^https?://127\.0\.0\.1(?::\d+)?$#',
];

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => $localOriginPatterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
