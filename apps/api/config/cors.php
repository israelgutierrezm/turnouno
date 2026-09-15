<?php

declare(strict_types=1);

return [

    /*
    | Paths that accept cross-origin requests. Includes the Sanctum CSRF cookie
    | and login/logout so the first-party SPAs can perform cookie auth.
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_ADMIN_URL', 'http://localhost:5173'),
        env('FRONTEND_PORTAL_URL', 'http://localhost:5174'),
        // Flujo multi-tenant (registro/directorio/login por estudio). Autenticacion
        // por bearer token, no por cookie, por lo que no necesita dominio stateful.
        env('FRONTEND_REGISTRO_URL', 'http://localhost:5175'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Correlation-ID'],

    'max_age' => 0,

    // Required for Sanctum cookie-based SPA authentication.
    'supports_credentials' => true,

];
