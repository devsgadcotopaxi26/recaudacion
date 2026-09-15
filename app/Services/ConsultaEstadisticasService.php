<?php

namespace App\Services;

use App\Models\ConsultaBancaria;

/**
 * Estadísticas de consultas de deuda vehicular, calculadas en tiempo real
 * desde la tabla `consulta_bancarias` (que BancaController::consultarDeuda
 * registra en cada consulta real).
 *
 * Reemplaza los contadores de Redis (`stats:consultas:*`) del Dashboard, que
 * dependían de VehiculoController::incrementarEstadisticas() — un flujo
 * (consulta ciudadana web) que en la práctica no genera los datos reales del
 * sistema, a diferencia del canal bancario que sí se usa y sí queda
 * registrado en `consulta_bancarias`.
 *
 * No tiene efectos secundarios: solo lee `consulta_bancarias`.
 */
class ConsultaEstadisticasService
{
    /**
     * Totales de consultas: histórico, hoy y en la hora en curso.
     */
    public function totales(): array
    {
        $hoy = date('Y-m-d');
        $inicioHoraActual = date('Y-m-d H:00:00');

        return [
            'total' => ConsultaBancaria::count(),
            'hoy' => ConsultaBancaria::whereDate('created_at', $hoy)->count(),
            'esta_hora' => ConsultaBancaria::where('created_at', '>=', $inicioHoraActual)->count(),
        ];
    }

    /**
     * Placas más consultadas (top N), histórico completo.
     */
    public function topPlacas(int $limite = 10): array
    {
        return ConsultaBancaria::selectRaw('placa, COUNT(*) as total')
            ->groupBy('placa')
            ->orderByDesc('total')
            ->limit($limite)
            ->get()
            ->map(fn($fila) => [
                'placa' => $fila->placa,
                'consultas' => (int) $fila->total,
            ])
            ->toArray();
    }

    /**
     * Consultas agrupadas por día, para los últimos $dias días (incluye hoy).
     */
    public function porDia(int $dias = 7): array
    {
        $desde = date('Y-m-d 00:00:00', strtotime('-' . ($dias - 1) . ' days'));

        $porFecha = ConsultaBancaria::where('created_at', '>=', $desde)
            ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
            ->groupBy('fecha')
            ->pluck('total', 'fecha');

        $resultado = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-{$i} days"));
            $resultado[] = [
                'fecha' => $fecha,
                'fecha_formateada' => date('d/m', strtotime($fecha)),
                'consultas' => (int) ($porFecha[$fecha] ?? 0),
            ];
        }

        return $resultado;
    }

    /**
     * Consultas agrupadas por hora, para las últimas $horas horas (incluye la actual).
     */
    public function porHora(int $horas = 24): array
    {
        $desde = date('Y-m-d H:00:00', strtotime('-' . ($horas - 1) . ' hours'));

        $porHora = ConsultaBancaria::where('created_at', '>=', $desde)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hora_bucket, COUNT(*) as total")
            ->groupBy('hora_bucket')
            ->pluck('total', 'hora_bucket');

        $resultado = [];
        for ($i = $horas - 1; $i >= 0; $i--) {
            $horaCalculo = date('Y-m-d H:00:00', strtotime("-{$i} hours"));
            $resultado[] = [
                'hora' => date('H:00', strtotime($horaCalculo)),
                'fecha_hora' => $horaCalculo,
                'consultas' => (int) ($porHora[$horaCalculo] ?? 0),
            ];
        }

        return $resultado;
    }
}
