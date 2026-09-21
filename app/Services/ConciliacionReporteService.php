<?php

namespace App\Services;

use App\Models\PagoDetalle;
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
 * Granularidad: una fila por año-detalle (PagoDetalle), no por transacción
 * — así los totales de caja por día/entidad/estado siguen cuadrando
 * exactamente igual que antes de la reestructuración cabecera/detalle
 * (finanzas ya usa esta pantalla contando por año fiscal, no por
 * operación de cobro). `comprobante`/`referencia_pago` ahora salen de la
 * transacción (cabecera) y se repiten entre las filas de un mismo pago
 * multi-año — antes cada año tenía un comprobante distinto; esto es un
 * cambio de comportamiento intencional, no una regresión.
 *
 * No tiene efectos secundarios: solo lee `pago_detalles`/`transacciones_pago`.
 */
class ConciliacionReporteService
{
    /**
     * Arma la query filtrada sobre `pago_detalles` (con su transacción
     * cargada, para los filtros/campos que viven en la cabecera).
     *
     * Filtros soportados (todos opcionales — el llamador decide cuáles
     * exigir mediante su propio Validator):
     *   fecha_desde, fecha_hasta (Y-m-d, requieren ambas para filtrar por rango),
     *   entidad (coincidencia parcial contra api_tokens.entidad_nombre, vía
     *     transacciones_pago.api_token_id),
     *   estado (pagado|pendiente|fallido|expirado|reversado),
     *   placa, anio_fiscal, codigo_consulta.
     */
    public function construirQuery(array $filtros): Builder
    {
        $query = PagoDetalle::query()->with('transaccionPago.apiToken');

        if (!empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta'])) {
            $query->whereBetween('pago_detalles.created_at', [
                $filtros['fecha_desde'] . ' 00:00:00',
                $filtros['fecha_hasta'] . ' 23:59:59',
            ]);
        }

        if (!empty($filtros['entidad']) || !empty($filtros['codigo_consulta'])) {
            $query->whereHas('transaccionPago', function ($q) use ($filtros) {
                if (!empty($filtros['entidad'])) {
                    $q->whereHas('apiToken', function ($q2) use ($filtros) {
                        $q2->where('entidad_nombre', 'like', '%' . $filtros['entidad'] . '%');
                    });
                }
                if (!empty($filtros['codigo_consulta'])) {
                    $q->where('codigo_consulta', $filtros['codigo_consulta']);
                }
            });
        }

        if (!empty($filtros['estado'])) {
            $query->where('pago_detalles.estado', $filtros['estado']);
        }

        if (!empty($filtros['placa'])) {
            $query->where('pago_detalles.placa', strtoupper($filtros['placa']));
        }

        if (!empty($filtros['anio_fiscal'])) {
            $query->where('pago_detalles.anio_fiscal', $filtros['anio_fiscal']);
        }

        return $query;
    }

    /**
     * Resumen general por estado, sobre una colección de PagoDetalle ya
     * cargada (debe representar TODO el conjunto filtrado, no solo una página).
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
     * Resumen agrupado por entidad recaudadora, sobre una colección de
     * PagoDetalle ya cargada (con su transaccionPago, debe representar
     * TODO el conjunto filtrado).
     */
    public function resumenPorEntidad(Collection $pagos): Collection
    {
        return $pagos->groupBy(function ($pago) {
            return $pago->transaccionPago->nombreEntidad();
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
     * fuente (`pago_detalles`) y el mismo criterio de fecha (`created_at`)
     * que usa el resto de este servicio.
     */
    public function recaudacionPorDia(int $dias = 7): array
    {
        $desde = date('Y-m-d 00:00:00', strtotime('-' . ($dias - 1) . ' days'));

        $porFecha = PagoDetalle::where('estado', 'pagado')
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
     * Formatea un PagoDetalle para el detalle del reporte. Misma forma que
     * ya devolvía BancaController::reporteAdminConciliacion (sin campos
     * nuevos), para no alterar el contrato del endpoint bancario existente
     * — salvo 'comprobante'/'referencia_pago', que ahora salen de la
     * transacción (compartidos entre años de un mismo pago multi-año, ver
     * docblock de la clase).
     */
    public function formatearDetalle(PagoDetalle $pago): array
    {
        $transaccion = $pago->transaccionPago;
        $datosAdicionales = $transaccion->datos_adicionales ?? [];

        return [
            'pago_id' => $transaccion->id,
            'comprobante' => $transaccion->comprobante(),
            'placa' => $pago->placa,
            'anio_fiscal' => $pago->anio_fiscal,
            'monto_impuesto' => round((float) $pago->monto_impuesto, 2),
            'monto_total' => round((float) $pago->monto_total, 2),
            'estado' => $pago->estado,
            'referencia_pago' => $transaccion->referencia_externa,
            'fecha_pago' => $transaccion->fecha_pago?->format('Y-m-d H:i:s'),
            'fecha_registro' => $pago->created_at->format('Y-m-d H:i:s'),
            'entidad_recaudadora' => $transaccion->nombreEntidad(),
            'codigo_consulta' => $transaccion->codigo_consulta,
            'metodo_pago' => $datosAdicionales['metodo_pago'] ?? null,
        ];
    }
}
