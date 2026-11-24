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
    | Inicio del cobro de rodaje por este sistema
    |--------------------------------------------------------------------------
    |
    | Fecha (Y-m-d) desde la que el GADPC cobra el rodaje con este sistema.
    | Si el último comprobante de matrícula del SRI se pagó en esta fecha o
    | después, todos los años que cubrió se cobran como rodaje pendiente
    | (salvo los que ya tengan pago local). Ver SriVehiculoService, rama
    | 'historial'.
    |
    */

    'inicio_cobro_rodaje' => env('SRI_INICIO_COBRO_RODAJE', '2026-01-01'),

    /*
    |--------------------------------------------------------------------------
    | Caché
    |--------------------------------------------------------------------------
    |
    | Dos TTL distintos a propósito, NO son intercambiables:
    |
    | - 'ttl': caché de datos crudos del SRI (SriVehiculoService::
    |   obtenerDetalleCompleto(), clave sri:detalle:{placa}) — marca,
    |   modelo, año, rubros tal como los reporta el SRI. No depende de
    |   pagos locales, es seguro cachearlo por horas.
    |
    | - 'ttl_deuda': caché de la respuesta YA COMBINADA con estado local
    |   (SriVehiculoService::consultarVehiculoCompleto(), clave
    |   sri_full_v3_{placa}) — esta SÍ incorpora una consulta a
    |   PagoDetalle local (rama 'historial'), así que un pago reciente
    |   puede quedar "escondido" detrás de este caché hasta que expire.
    |   Corto a propósito, y además se invalida activamente al registrar
    |   un pago (ver TransaccionPago::marcarComoPagado() y
    |   BancaController::registrarPago()) — el TTL corto es solo la red
    |   de seguridad para los casos que no pasan por esa invalidación.
    |
    */

    'cache' => [
        'enabled' => env('SRI_CACHE_ENABLED', true),
        'ttl' => env('SRI_CACHE_TTL', 86400), // 24 horas — solo datos crudos del SRI
        'ttl_deuda' => env('SRI_CACHE_TTL_DEUDA', 300), // 5 minutos — incluye estado de pago local
    ],
];
