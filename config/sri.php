<?php

return [
    /*
    |--------------------------------------------------------------------------
    | URLs del SRI
    |--------------------------------------------------------------------------
    |
    | URLs de los servicios web del SRI para consulta de vehículos
    |
    */

    'base_url' => 'https://srienlinea.sri.gob.ec/sri-matriculacion-vehicular-recaudacion-servicio-internet/rest',

    'endpoints' => [
        'verificacion' => '/verificacion',
        'detalle_vehiculo' => '/matriculacion/valor', // Endpoint unificado (nuevo)
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Timeout
    |--------------------------------------------------------------------------
    */

    'timeout' => env('SRI_TIMEOUT', 10), // segundos

    /*
    |--------------------------------------------------------------------------
    | Caché
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('SRI_CACHE_ENABLED', true),
        'ttl' => env('SRI_CACHE_TTL', 3600), // 1 hora por defecto
    ],
];
