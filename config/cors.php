<?php

/*
| CORS para que el frontend web (Franc) consuma la API desde otro origen.
| Los orígenes permitidos se definen en CORS_ALLOWED_ORIGINS, separados por coma.
| La API usa tokens Bearer (Sanctum), no cookies, por eso no se envían credenciales.
*/

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000'))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
