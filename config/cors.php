<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Cookie-based SPA auth (Sanctum stateful) requires credentials to be
    | included in cross-origin requests, so `supports_credentials` must be
    | true and `allowed_origins` must list exact origins (no wildcard).
    |
    | In development the Vite proxy makes requests appear same-origin to
    | the browser, so CORS headers are mainly needed for direct API clients
    | and for production deployments where the SPA and API are on separate
    | subdomains.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // In production, set CORS_ALLOWED_ORIGINS=https://yourdomain.com in .env
    'allowed_origins' => array_values(array_filter(array_merge(
        [
            'http://localhost:5173',
            'http://localhost:3000',
            'http://localhost:8000',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:8000',
        ],
        array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))))
    ))),

    'allowed_origins_patterns' => [],

    // Explicit allowlist — never use '*' with credentials:true (leaks cookies to any origin)
    'allowed_headers' => [
        'Content-Type',
        'Accept',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        'Authorization',
    ],

    'exposed_headers' => [],

    // Cache preflight for 24 h in production (set CORS_MAX_AGE=86400 in .env)
    'max_age' => (int) env('CORS_MAX_AGE', 0),

    // Required for cookie / session auth.
    'supports_credentials' => true,

];
