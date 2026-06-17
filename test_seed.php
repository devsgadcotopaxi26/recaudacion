<?php

// Ejecutar con: php test_seed.php
// Desde el contenedor: docker exec recaudacion_app php test_seed.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Crear entidades
DB::table('api_tokens')->insert([
    [
        'entidad_nombre' => 'Cooperativa Cotopaxi Ltda.',
        'token' => 'token_coop_cotopaxi',
        'activo' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'entidad_nombre' => 'Cooperativa Sierra Centro',
        'token' => 'token_coop_sierra',
        'activo' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ],
]);

$c = DB::table('api_tokens')->where('entidad_nombre', 'Cooperativa Cotopaxi Ltda.')->value('id');
$s = DB::table('api_tokens')->where('entidad_nombre', 'Cooperativa Sierra Centro')->value('id');
$b = DB::table('api_tokens')->where('entidad_nombre', 'like', '%Prueba%')->value('id');

echo "IDs: Cotopaxi=$c, Sierra=$s, Banco=$b\n";

DB::table('pagos')->insert([
    [
        'placa' => 'XYZ5678', 'anio_fiscal' => 2026, 'monto_impuesto' => 15.50,
        'monto_total' => 15.50, 'estado' => 'pagado', 'referencia_pago' => 'COOP-COT-001',
        'fecha_pago' => '2026-06-10', 'api_token_id' => $c,
        'datos_adicionales' => json_encode(['metodo_pago' => 'API_Bancaria', 'entidad_recaudadora' => 'Cooperativa Cotopaxi Ltda.']),
        'created_at' => '2026-06-10 09:30:00', 'updated_at' => now(),
    ],
    [
        'placa' => 'DEF9012', 'anio_fiscal' => 2026, 'monto_impuesto' => 22.30,
        'monto_total' => 22.30, 'estado' => 'pagado', 'referencia_pago' => 'COOP-COT-002',
        'fecha_pago' => '2026-06-11', 'api_token_id' => $c,
        'datos_adicionales' => json_encode(['metodo_pago' => 'API_Bancaria', 'entidad_recaudadora' => 'Cooperativa Cotopaxi Ltda.']),
        'created_at' => '2026-06-11 14:20:00', 'updated_at' => now(),
    ],
    [
        'placa' => 'GHI3456', 'anio_fiscal' => 2026, 'monto_impuesto' => 8.75,
        'monto_total' => 8.75, 'estado' => 'pagado', 'referencia_pago' => 'COOP-SIE-001',
        'fecha_pago' => '2026-06-12', 'api_token_id' => $s,
        'datos_adicionales' => json_encode(['metodo_pago' => 'API_Bancaria', 'entidad_recaudadora' => 'Cooperativa Sierra Centro']),
        'created_at' => '2026-06-12 10:15:00', 'updated_at' => now(),
    ],
    [
        'placa' => 'JKL7890', 'anio_fiscal' => 2026, 'monto_impuesto' => 30.00,
        'monto_total' => 30.00, 'estado' => 'pagado', 'referencia_pago' => 'COOP-SIE-002',
        'fecha_pago' => '2026-06-13', 'api_token_id' => $s,
        'datos_adicionales' => json_encode(['metodo_pago' => 'API_Bancaria', 'entidad_recaudadora' => 'Cooperativa Sierra Centro']),
        'created_at' => '2026-06-13 16:45:00', 'updated_at' => now(),
    ],
    [
        'placa' => 'ABC1234', 'anio_fiscal' => 2025, 'monto_impuesto' => 11.10,
        'monto_total' => 11.10, 'estado' => 'pagado', 'referencia_pago' => 'BANCO-TEST-002',
        'fecha_pago' => '2026-06-14', 'api_token_id' => $b,
        'datos_adicionales' => json_encode(['metodo_pago' => 'API_Bancaria', 'entidad_recaudadora' => 'Banco de Prueba S.A.']),
        'created_at' => '2026-06-14 08:00:00', 'updated_at' => now(),
    ],
    [
        'placa' => 'XYZ5678', 'anio_fiscal' => 2025, 'monto_impuesto' => 15.50,
        'monto_total' => 15.50, 'estado' => 'fallido', 'referencia_pago' => 'COOP-COT-003',
        'fecha_pago' => null, 'api_token_id' => $c,
        'datos_adicionales' => json_encode(['metodo_pago' => 'API_Bancaria', 'entidad_recaudadora' => 'Cooperativa Cotopaxi Ltda.']),
        'created_at' => '2026-06-14 11:30:00', 'updated_at' => now(),
    ],
]);

echo "Listo: " . DB::table('pagos')->count() . " pagos totales\n";
