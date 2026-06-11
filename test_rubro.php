<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
$x = Http::get('https://srienlinea.sri.gob.ec/sri-matriculacion-vehicular-recaudacion-servicio-internet/rest/ConsultaComponente/obtenerListaComponentesPorCodigoConsultaRubro?codigoConsultaRubro=3514569170')->json();
file_put_contents('out2.json', json_encode($x, JSON_PRETTY_PRINT));
