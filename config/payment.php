<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de la pasarela de pagos
    |
    */

    'api_url' => env('PAYMENT_GATEWAY_URL', ''),
    'api_key' => env('PAYMENT_GATEWAY_API_KEY', ''),
    'secret_key' => env('PAYMENT_GATEWAY_SECRET_KEY', ''),
    'timeout' => env('PAYMENT_GATEWAY_TIMEOUT', 30),

    'callback_url' => env('PAYMENT_GATEWAY_CALLBACK_URL', env('APP_URL') . '/pago/callback'),
    'webhook_url' => env('PAYMENT_GATEWAY_WEBHOOK_URL', env('APP_URL') . '/webhook/pago'),

];
