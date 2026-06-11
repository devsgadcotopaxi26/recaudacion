<?php

namespace App\Console\Commands;

use App\Models\Pago;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class SincronizarEstadisticasRecaudacion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stats:sync-recaudacion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar estadísticas de recaudación desde pagos existentes en la base de datos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Sincronizando estadísticas de recaudación...');

        // Obtener todos los pagos completados
        $pagos = Pago::where('estado', 'pagado')->get();

        if ($pagos->isEmpty()) {
            $this->warn('⚠️  No se encontraron pagos completados');
            return 0;
        }

        $this->info("📊 Procesando {$pagos->count()} pagos...");

        // Resetear estadísticas de recaudación
        $this->info('🗑️  Limpiando estadísticas anteriores de recaudación...');
        $keysRecaudacion = Redis::keys('stats:recaudacion:*');
        $keysPagos = Redis::keys('stats:pagos:completados:*');

        if (count($keysRecaudacion) > 0) {
            Redis::del(...$keysRecaudacion);
        }
        if (count($keysPagos) > 0) {
            Redis::del(...$keysPagos);
        }

        // Procesar cada pago
        $totalRecaudado = 0;
        $pagosPorDia = [];
        $montosPorDia = [];

        $progressBar = $this->output->createProgressBar($pagos->count());
        $progressBar->start();

        foreach ($pagos as $pago) {
            $monto = floatval($pago->monto_total);
            $fecha = $pago->fecha_pago ? $pago->fecha_pago->format('Y-m-d') : date('Y-m-d');

            // Incrementar monto total
            $totalRecaudado += $monto;

            // Acumular por día
            if (!isset($montosPorDia[$fecha])) {
                $montosPorDia[$fecha] = 0;
                $pagosPorDia[$fecha] = 0;
            }

            $montosPorDia[$fecha] += $monto;
            $pagosPorDia[$fecha]++;

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Guardar en Redis
        $this->info('💾 Guardando en Redis...');

        // Total general
        Redis::set('stats:recaudacion:total', $totalRecaudado);
        Redis::set('stats:pagos:completados:total', $pagos->count());

        // Por día
        foreach ($montosPorDia as $fecha => $monto) {
            Redis::setex("stats:recaudacion:dia:{$fecha}", 5184000, $monto); // 60 días
            Redis::setex("stats:pagos:completados:dia:{$fecha}", 5184000, $pagosPorDia[$fecha]);
        }

        // Mostrar resumen
        $this->newLine();
        $this->info('✅ Sincronización completada');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total Pagos Procesados', $pagos->count()],
                ['Monto Total Recaudado', '$' . number_format($totalRecaudado, 2)],
                ['Días con Pagos', count($montosPorDia)],
                ['Promedio por Pago', '$' . number_format($pagos->count() > 0 ? $totalRecaudado / $pagos->count() : 0, 2)],
            ]
        );

        return 0;
    }
}
