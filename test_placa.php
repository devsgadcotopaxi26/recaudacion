<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\SriVehiculoService;

/**
 * Proxy de prueba para forzar el camino de historial de pagos
 */
class TestServiceProxy extends SriVehiculoService {
    public $forceHistory = false;

    public function obtenerDetalleCompleto(string $placa): array {
        // Obtenemos los datos reales pero borramos caché para la prueba
        \Illuminate\Support\Facades\Cache::forget("sri:detalle:{$placa}");
        $data = parent::obtenerDetalleCompleto($placa);
        
        if ($this->forceHistory) {
            // Si forzamos historial, simulamos que el primer endpoint no trae deudas
            $data['deudas'] = null; 
        }
        return $data;
    }
}

$placa = $argv[1] ?? 'PCD1825';
$anioSimulado = isset($argv[2]) ? intval($argv[2]) : 2023;
$forceHistory = in_array('--historial', $argv);

$service = new TestServiceProxy();
$service->forceHistory = $forceHistory;

echo "--- CONSULTANDO: $placa | REF AÑO: $anioSimulado " . ($forceHistory ? "[MODO HISTORIAL]" : "[MODO DEUDA]") . " ---\n";

try {
    $res = $service->consultarVehiculoCompleto($placa, $anioSimulado);
    echo "JSON RESULTADO:\n";
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
