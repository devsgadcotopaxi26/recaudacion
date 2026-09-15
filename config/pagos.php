<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pasarela de pago ciudadana
    |--------------------------------------------------------------------------
    |
    | Controla si la pantalla pública /consultar muestra el botón "Proceder
    | al Pago" y las instrucciones asociadas. Aún no entra en producción:
    | por defecto queda deshabilitada (false) hasta que se habilite.
    |
    */

    'pasarela_ciudadana_habilitada' => env('PASARELA_CIUDADANA_HABILITADA', false),
];
