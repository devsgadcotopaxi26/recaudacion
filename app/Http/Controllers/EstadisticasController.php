<?php

namespace App\Http\Controllers;

use App\Services\ConciliacionReporteService;
use App\Services\ConsultaEstadisticasService;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;

class EstadisticasController extends Controller
{
    public function __construct(
        private ConciliacionReporteService $conciliacionService,
        private ConsultaEstadisticasService $consultaService
    ) {
    }

    /**
     * Mostrar panel de estadísticas.
     *
     * Recaudación y Consultas de Vehículos se calculan en tiempo real desde
     * las tablas reales (`pago_detalles`/`transacciones_pago` y
     * `consulta_bancarias`), reutilizando ConciliacionReporteService (la
     * misma lógica que ya usa el Reporte de Conciliación) y el nuevo
     * ConsultaEstadisticasService. Ya no dependen de los contadores
     * `stats:*` de Redis, que solo se incrementaban desde el flujo
     * ciudadano (VehiculoController / TransaccionPago::marcarComoPagado),
     * un canal que en la práctica no genera los datos reales de este
     * sistema (todo se creó vía la API bancaria). Esas llamadas a Redis se
     * dejan intactas en su código original por si ese flujo llega a usarse.
     */
    public function index()
    {
        $hoy = date('Y-m-d');

        // ── Recaudación (tiempo real, vía ConciliacionReporteService) ──────
        $pagosHoy = $this->conciliacionService
            ->construirQuery(['fecha_desde' => $hoy, 'fecha_hasta' => $hoy])
            ->get();
        $resumenHoy = $this->conciliacionService->resumenGeneral($pagosHoy);

        $todosLosPagos = $this->conciliacionService->construirQuery([])->get();
        $resumenTotal = $this->conciliacionService->resumenGeneral($todosLosPagos);

        $recaudacionTotal = $resumenTotal['pagados']['monto_total'];
        $pagosCompletadosTotal = $resumenTotal['pagados']['cantidad'];
        $recaudacionHoy = $resumenHoy['pagados']['monto_total'];
        $pagosCompletadosHoy = $resumenHoy['pagados']['cantidad'];

        $promedioMontoPago = $pagosCompletadosTotal > 0
            ? round($recaudacionTotal / $pagosCompletadosTotal, 2)
            : 0;

        $recaudacionPorDia = $this->conciliacionService->recaudacionPorDia(7);

        // ── Consultas de vehículos (tiempo real, vía ConsultaEstadisticasService) ──
        $consultas = $this->consultaService->totales();
        $topPlacas = $this->consultaService->topPlacas(10);
        $consultasPorDia = $this->consultaService->porDia(7);
        $consultasPorHora = $this->consultaService->porHora(24);

        // Información de Redis (sigue siendo real: uso de caché/sesiones/colas,
        // no relacionado con las estadísticas de arriba)
        try {
            $info = Redis::connection()->client()->info();
            $memoryUsed = $info['Memory']['used_memory_human'] ?? 'N/A';
        } catch (\Exception $e) {
            $memoryUsed = 'N/A';
        }

        $totalKeys = Redis::dbsize();

        return Inertia::render('Dashboard/Estadisticas', [
            'estadisticas' => [
                'consultas_total' => $consultas['total'],
                'consultas_hoy' => $consultas['hoy'],
                'consultas_esta_hora' => $consultas['esta_hora'],
                'top_placas' => $topPlacas,
                'consultas_por_hora' => $consultasPorHora,
                'consultas_por_dia' => $consultasPorDia,
                // Estadísticas de recaudación
                'recaudacion_total' => $recaudacionTotal,
                'recaudacion_hoy' => $recaudacionHoy,
                'pagos_completados_hoy' => $pagosCompletadosHoy,
                'pagos_completados_total' => $pagosCompletadosTotal,
                'promedio_monto_pago' => $promedioMontoPago,
                'recaudacion_por_dia' => $recaudacionPorDia,
            ],
            'redis_info' => [
                'memoria_usada' => $memoryUsed,
                'total_claves' => $totalKeys,
            ],
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Resetear estadísticas (solo en local)
     */
    public function reset()
    {
        if (!app()->environment('local')) {
            return redirect()->back()->with('error', 'No disponible en producción');
        }

        // Resetear contadores
        $keys = Redis::keys('stats:*');

        if (count($keys) > 0) {
            Redis::del(...$keys);
        }

        return redirect()->back()->with('success', 'Estadísticas reseteadas exitosamente');
    }
}
