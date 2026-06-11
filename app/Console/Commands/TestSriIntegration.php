<?php

namespace App\Console\Commands;

use App\Services\SriVehiculoService;
use Illuminate\Console\Command;

class TestSriIntegration extends Command
{
    protected $signature = 'sri:test {placa?}';
    protected $description = 'Probar integración con API del SRI';

    public function handle()
    {
        $placa = $this->argument('placa') ?? 'TBA5103';

        $this->info("🔍 Consultando placa: {$placa}");
        $this->newLine();

        try {
            $service = new SriVehiculoService();

            // Consulta completa (ahora en solo 2 llamadas al SRI)
            $this->info("📡 Consultando datos completos...");

            $resultado = $service->consultarVehiculoCompleto($placa);

            // Mostrar datos del vehículo
            $this->newLine();
            $this->info("✅ Datos del Vehículo:");
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['Placa', $resultado['vehiculo']['placa']],
                    ['Marca', $resultado['vehiculo']['marca']],
                    ['Modelo', $resultado['vehiculo']['modelo']],
                    ['Año', $resultado['vehiculo']['anio']],
                    ['Clase', $resultado['vehiculo']['clase']],
                ]
            );

            // Mostrar rubros
            $this->newLine();
            $this->info("✅ Rubros ({$this->count($resultado['rubros'])}):");

            $rubrosTable = [];
            foreach ($resultado['rubros'] as $rubro) {
                $rubrosTable[] = [
                    $rubro['descripcion'],
                    '$' . number_format($rubro['valor'], 2),
                    $rubro['beneficiario'],
                ];
            }

            $this->table(
                ['Descripción', 'Valor', 'Beneficiario'],
                $rubrosTable
            );

            // Mostrar cálculos
            $this->newLine();
            $this->info("🧮 Cálculos:");
            $this->table(
                ['Concepto', 'Valor'],
                [
                    ['Valor Matrícula', '$' . number_format($resultado['valor_matricula'], 2)],
                    ['Impuesto (10% con min/max)', '$' . number_format($resultado['impuesto'], 2)],
                    ['Total a Pagar', '$' . number_format($resultado['total_a_pagar'], 2)],
                ]
            );

            $this->newLine();
            $this->info("✅ Prueba completada exitosamente");

            return 0;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }

    private function count($array): int
    {
        return is_array($array) ? count($array) : 0;
    }
}
