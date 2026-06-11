<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = new \App\Services\SriVehiculoService();

echo "--- SIMULACIÓN REQUERIDA POR USUARIO ---\n";
echo "Placa: PCD1825\n";
echo "Año Actual Simulado: 2023\n";

// Datos del detalle de pago 49634895 proporcionados por el usuario
$rubrosRaw = [
    ["descripcionRubro" => "IMPUESTO A LA PROPIEDAD", "descripcionComponente" => "Impuesto", "anio" => 2022, "valor" => "7,67"],
    ["descripcionRubro" => "IMPUESTO A LA PROPIEDAD", "descripcionComponente" => "Interes", "anio" => 2022, "valor" => "0,21"],
    ["descripcionRubro" => "IMPUESTO A LA PROPIEDAD", "descripcionComponente" => "Impuesto", "anio" => 2023, "valor" => "7,67"],
    ["descripcionRubro" => "TASA SPPAT", "descripcionComponente" => "Recargo", "anio" => 2022, "valor" => "34,84"],
    ["descripcionRubro" => "TASA SPPAT", "descripcionComponente" => "Tasa", "anio" => 2022, "valor" => "38,71"],
    ["descripcionRubro" => "TASA SPPAT", "descripcionComponente" => "Recargo", "anio" => 2023, "valor" => "21,54"],
    ["descripcionRubro" => "TASA SPPAT", "descripcionComponente" => "Tasa", "anio" => 2023, "valor" => "47,86"],
    ["descripcionRubro" => "TASAS ANT", "descripcionComponente" => "Tasa", "anio" => 2022, "valor" => "36"],
    ["descripcionRubro" => "TASAS ANT", "descripcionComponente" => "Recargo", "anio" => 2023, "valor" => "25"],
    ["descripcionRubro" => "TASAS ANT", "descripcionComponente" => "Tasa", "anio" => 2023, "valor" => "36"]
];

$detalleSimulado = [
    'desde_pago' => true,
    'placa' => 'PCD1825',
    'total' => 255.5, // Monto del pago 49634895
    'rubros_raw' => $rubrosRaw
];

$anioSimulado = 2023;
$resultado = $service->calcularDesglosePorAnio($detalleSimulado, $anioSimulado);

echo "\nRESULTADO DEL DESGLOSE (SIMULADO 2023):\n";
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Validar contra lo esperado
// 2023 -> Subtotal 91.53 -> Rodaje 9.15 -> Mora (detecta recargo) -> 0.92
// 2022 -> Subtotal 82.38 -> Rodaje 8.24 -> Mora (1 año atraso) -> 0.82
