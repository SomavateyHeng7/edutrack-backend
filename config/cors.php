<?php

return [

    // Apply CORS to these paths
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    // Allow all HTTP methods for those paths
    'allowed_methods' => ['*'],

    // Frontend origins that are allowed to call this API
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],

    'allowed_origins_patterns' => [],

    // Allow all headers (you can restrict later)
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Important for Sanctum / cookies
    'supports_credentials' => true,
];
