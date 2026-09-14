<?php

namespace App\Services;

use App\Models\Pago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Construye el reporte de conciliación de pagos (filtros, resumen y detalle).
 *
 * Única implementación de esta lógica en el sistema: la usan tanto el
 * endpoint bancario `BancaController::reporteAdminConciliacion` (protegido
 * con api.token, para entidades/GAD) como el panel administrativo web
 * `ReporteConciliacionController` (sesión + rol admin), para no duplicar
 * las reglas de filtrado ni el cálculo de totales.
 *
 * No tiene efectos secundarios: solo lee la tabla `pagos`.
 */
class ConciliacionReporteService
{
    /**
     * Arma la query filtrada sobre `pagos`.
     *
     * Filtros soportados (todos opcionales — el llamador decide cuáles
     * exigir mediante su propio Validator):
     *   fecha_desde, fecha_hasta (Y-m-d, requieren ambas para filtrar por rango),
     *   entidad (coincidencia parcial contra datos_adicionales->entidad_recaudadora),
     *   estado (pagado|pendiente|fallido|expirado),
     *   placa, anio_fiscal, codigo_consulta.
     */
    public function construirQuery(array $filtros): Builder
    {
        $query = Pago::query();

        if (!empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta'])) {
            $query->whereBetween('created_at', [
                $filtros['fecha_desde'] . ' 00:00:00',
                $filtros['fecha_hasta'] . ' 23:59:59',
            ]);
        }

        if (!empty($filtros['entidad'])) {
            $query->where('datos_adicionales->entidad_recaudadora', 'like', '%' . $filtros['entidad'] . '%');
        }

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['placa'])) {
            $query->where('placa', strtoupper($filtros['placa']));
        }

        if (!empty($filtros['anio_fiscal'])) {
            $query->where('anio_fiscal', $filtros['anio_fiscal']);
        }

        if (!empty($filtros['codigo_consulta'])) {
            $query->where('datos_adicionales->codigo_consulta', $filtros['codigo_consulta']);
        }

        return $query;
    }

    /**
     * Resumen general por estado, sobre una colección de Pago ya cargada
     * (debe representar TODO el conjunto filtrado, no solo una página).
     */
    public function resumenGeneral(Collection $pagos): array
    {
        $pagados = $pagos->where('estado', 'pagado');
        $pendientes = $pagos->where('estado', 'pendiente');
        $fallidos = $pagos->where('estado', 'fallido');

        return [
            'total_transacciones' => $pagos->count(),
            'pagados' => [
                'cantidad' => $pagados->count(),
                'monto_total' => round($pagados->sum('monto_total'), 2),
            ],
            'pendientes' => [
                'cantidad' => $pendientes->count(),
                'monto_total' => round($pendientes->sum('monto_total'), 2),
            ],
            'fallidos' => [
                'cantidad' => $fallidos->count(),
                'monto_total' => round($fallidos->sum('monto_total'), 2),
            ],
        ];
    }

    /**
     * Resumen agrupado por entidad recaudadora, sobre una colección de Pago
     * ya cargada (debe representar TODO el conjunto filtrado).
     */
    public function resumenPorEntidad(Collection $pagos): Collection
    {
        return $pagos->groupBy(function ($pago) {
            return $pago->datos_adicionales['entidad_recaudadora'] ?? 'Sin entidad';
        })->map(function ($pagosPorEntidad, $nombreEntidad) {
            $pagadosEntidad = $pagosPorEntidad->where('estado', 'pagado');
            return [
                'entidad' => $nombreEntidad,
                'total_transacciones' => $pagosPorEntidad->count(),
                'pagados' => $pagadosEntidad->count(),
                'monto_total_pagado' => round($pagadosEntidad->sum('monto_total'), 2),
                'pendientes' => $pagosPorEntidad->where('estado', 'pendiente')->count(),
                'fallidos' => $pagosPorEntidad->where('estado', 'fallido')->count(),
            ];
        })->values();
    }

    /**
     * Recaudación (solo pagos con estado='pagado') agrupada por día,
     * para los últimos $dias días (incluye hoy). Usada por el Dashboard
     * para la gráfica "Recaudación por Día" en tiempo real, con la misma
     * fuente (`pagos`) y el mismo criterio de fecha (`created_at`) que
     * usa el resto de este servicio.
     */
    public function recaudacionPorDia(int $dias = 7): array
    {
        $desde = date('Y-m-d 00:00:00', strtotime('-' . ($dias - 1) . ' days'));

        $porFecha = Pago::where('estado', 'pagado')
            ->where('created_at', '>=', $desde)
            ->selectRaw('DATE(created_at) as fecha, SUM(monto_total) as monto')
            ->groupBy('fecha')
            ->pluck('monto', 'fecha');

        $resultado = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-{$i} days"));
            $resultado[] = [
                'fecha' => $fecha,
                'fecha_formateada' => date('d/m', strtotime($fecha)),
                'monto' => round((float) ($porFecha[$fecha] ?? 0), 2),
            ];
        }

        return $resultado;
    }

    /**
     * Formatea un Pago para el detalle del reporte. Misma forma que ya
     * devolvía BancaController::reporteAdminConciliacion (sin campos nuevos),
     * para no alterar el contrato del endpoint bancario existente.
     */
    public function formatearDetalle(Pago $pago): array
    {
        $datosAdicionales = $pago->datos_adicionales ?? [];

        return [
            'pago_id' => $pago->id,
            'comprobante' => 'PAG-' . str_pad($pago->id, 6, '0', STR_PAD_LEFT),
            'placa' => $pago->placa,
            'anio_fiscal' => $pago->anio_fiscal,
            'monto_impuesto' => round((float) $pago->monto_impuesto, 2),
            'monto_total' => round((float) $pago->monto_total, 2),
            'estado' => $pago->estado,
            'referencia_pago' => $pago->referencia_pago,
            'fecha_pago' => $pago->fecha_pago?->format('Y-m-d H:i:s'),
            'fecha_registro' => $pago->created_at->format('Y-m-d H:i:s'),
            'entidad_recaudadora' => $datosAdicionales['entidad_recaudadora'] ?? null,
            'codigo_consulta' => $datosAdicionales['codigo_consulta'] ?? null,
            'metodo_pago' => $datosAdicionales['metodo_pago'] ?? null,
        ];
    }
}
