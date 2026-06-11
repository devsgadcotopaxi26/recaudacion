<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;

class EstadisticasController extends Controller
{
    /**
     * Mostrar panel de estadísticas
     */
    public function index()
    {
        $fecha = date('Y-m-d');
        $hora = date('Y-m-d:H');

        // Obtener estadísticas básicas de consultas
        $consultasTotal = (int) Redis::get('stats:consultas:total') ?? 0;
        $consultasHoy = (int) Redis::get("stats:consultas:dia:{$fecha}") ?? 0;
        $consultasEstaHora = (int) Redis::get("stats:consultas:hora:{$hora}") ?? 0;

        // Top 10 placas más consultadas
        $placasPopulares = Redis::zrevrange('stats:placas:populares', 0, 9, 'WITHSCORES');
        $topPlacas = [];

        for ($i = 0; $i < count($placasPopulares); $i += 2) {
            if (isset($placasPopulares[$i]) && isset($placasPopulares[$i + 1])) {
                $topPlacas[] = [
                    'placa' => $placasPopulares[$i],
                    'consultas' => (int) $placasPopulares[$i + 1]
                ];
            }
        }

        // Consultas por hora (últimas 24 horas)
        $consultasPorHora = [];
        for ($i = 23; $i >= 0; $i--) {
            $horaCalculo = date('Y-m-d:H', strtotime("-{$i} hours"));
            $consultas = (int) Redis::get("stats:consultas:hora:{$horaCalculo}") ?? 0;
            $consultasPorHora[] = [
                'hora' => date('H:00', strtotime("-{$i} hours")),
                'fecha_hora' => $horaCalculo,
                'consultas' => $consultas
            ];
        }

        // Consultas por día (últimos 7 días)
        $consultasPorDia = [];
        for ($i = 6; $i >= 0; $i--) {
            $diaCalculo = date('Y-m-d', strtotime("-{$i} days"));
            $consultas = (int) Redis::get("stats:consultas:dia:{$diaCalculo}") ?? 0;
            $consultasPorDia[] = [
                'fecha' => $diaCalculo,
                'fecha_formateada' => date('d/m', strtotime($diaCalculo)),
                'consultas' => $consultas
            ];
        }

        // Información de Redis
        try {
            $info = Redis::connection()->client()->info();
            $memoryUsed = $info['Memory']['used_memory_human'] ?? 'N/A';
        } catch (\Exception $e) {
            $memoryUsed = 'N/A';
        }

        $totalKeys = Redis::dbsize();

        // Estadísticas de recaudación
        $recaudacionTotal = (float) Redis::get('stats:recaudacion:total') ?? 0;
        $recaudacionHoy = (float) Redis::get("stats:recaudacion:dia:{$fecha}") ?? 0;
        $pagosCompletadosHoy = (int) Redis::get("stats:pagos:completados:dia:{$fecha}") ?? 0;
        $pagosCompletadosTotal = (int) Redis::get('stats:pagos:completados:total') ?? 0;

        // Calcular promedio
        $promedioMontoPago = $pagosCompletadosTotal > 0
            ? round($recaudacionTotal / $pagosCompletadosTotal, 2)
            : 0;

        // Recaudación por día (últimos 7 días)
        $recaudacionPorDia = [];
        for ($i = 6; $i >= 0; $i--) {
            $diaCalculo = date('Y-m-d', strtotime("-{$i} days"));
            $monto = (float) Redis::get("stats:recaudacion:dia:{$diaCalculo}") ?? 0;
            $recaudacionPorDia[] = [
                'fecha' => $diaCalculo,
                'fecha_formateada' => date('d/m', strtotime($diaCalculo)),
                'monto' => $monto
            ];
        }

        return Inertia::render('Dashboard/Estadisticas', [
            'estadisticas' => [
                'consultas_total' => $consultasTotal,
                'consultas_hoy' => $consultasHoy,
                'consultas_esta_hora' => $consultasEstaHora,
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
