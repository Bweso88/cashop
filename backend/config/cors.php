<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // Les applications web et admin passent par leur BFF (appels serveur à serveur, sans CORS) ;
    // les apps mobiles natives ne sont pas soumises au CORS. Seuls des clients navigateur
    // explicitement listés (ex. aperçu web de l'app Flutter en développement) sont autorisés.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key', 'X-Correlation-Id'],

    'exposed_headers' => ['X-Correlation-Id', 'Retry-After'],

    'max_age' => 0,

    'supports_credentials' => false,

];
