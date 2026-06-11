<?php

namespace App\Console\Commands;

use App\Services\SriVehiculoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class VerificarCacheSri extends Command
{
    protected $signature = 'sri:verify-cache {placa}';
    protected $description = 'Verificar si los datos vienen de caché o del SRI';

    public function handle()
    {
        $placa = strtoupper($this->argument('placa'));

        $this->info("🔍 Verificando caché para placa: {$placa}");
        $this->newLine();

        // Verificar qué hay en caché
        $cacheVehiculo = Cache::get("sri:vehiculo:{$placa}");

        if ($cacheVehiculo) {
            $this->info("✅ HAY DATOS EN CACHÉ");
            $this->info("   Clave: sri:vehiculo:{$placa}");
            $this->info("   Código: " . ($cacheVehiculo['codigoVehiculo'] ?? 'N/A'));
            $this->newLine();
        } else {
            $this->warn("❌ NO HAY DATOS EN CACHÉ");
            $this->info("   La próxima consulta irá al SRI");
            $this->newLine();
        }

        // Hacer consulta y medir tiempo
        $this->info("⏱️  Realizando consulta...");

        $inicio = microtime(true);

        try {
            $service = new SriVehiculoService();
            $resultado = $service->consultarVehiculoCompleto($placa);

            $fin = microtime(true);
            $tiempo = round(($fin - $inicio) * 1000, 2); // milisegundos

            $this->newLine();

            if ($cacheVehiculo) {
                $this->info("🚀 DATOS OBTENIDOS DEL CACHÉ");
                $this->info("   Tiempo: {$tiempo} ms (muy rápido)");
            } else {
                $this->info("🌐 DATOS OBTENIDOS DEL SRI");
                $this->info("   Tiempo: {$tiempo} ms (llamada al WS)");
            }

            $this->newLine();

            // Mostrar resultado
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['Placa', $resultado['vehiculo']['numeroPlaca'] ?? 'N/A'],
                    ['Marca', $resultado['vehiculo']['descripcionMarca'] ?? 'N/A'],
                    ['Rubros', count($resultado['rubros'])],
                    ['Valor Matrícula', '$' . $resultado['valor_matricula']],
                    ['Impuesto', '$' . $resultado['impuesto']],
                ]
            );

            $this->newLine();
            $this->info("💡 CONSEJO: Ejecuta este comando 2 veces seguidas");
            $this->info("   1ra vez: Consulta al SRI (~2000ms)");
            $this->info("   2da vez: Desde caché (~50ms)");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }
}
