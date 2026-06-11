<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Services\SriVehiculoService::class);
$x = $service->consultarVehiculoCompleto('PBR0055');
file_put_contents('out4.json', json_encode($x, JSON_PRETTY_PRINT));
