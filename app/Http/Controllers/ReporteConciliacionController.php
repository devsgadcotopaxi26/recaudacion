<?php

namespace App\Http\Controllers;

use App\Services\ConciliacionReporteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ReporteConciliacionController extends Controller
{
    public function __construct(private ConciliacionReporteService $service)
    {
    }

    /**
     * Mostrar la pantalla de Reporte de Conciliación
     */
    public function index()
    {
        return Inertia::render('Admin/ReporteConciliacion');
    }

    /**
     * Ejecutar la consulta del reporte de conciliación (paginado).
     *
     * Reutiliza ConciliacionReporteService, la misma lógica de filtros y
     * cálculo de totales que usa BancaController@reporteAdminConciliacion,
     * para no mantener dos implementaciones. A diferencia de ese endpoint
     * (protegido con token bancario y con fechas obligatorias), este vive
     * bajo la sesión/rol del panel admin y permite consultar sin fechas.
     */
    public function consultar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_desde' => 'nullable|date_format:Y-m-d',
            'fecha_hasta' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_desde',
            'entidad' => 'nullable|string|max:100',
            'estado' => 'nullable|in:pagado,pendiente,fallido,expirado',
            'placa' => 'nullable|string|max:10',
            'anio_fiscal' => 'nullable|integer|min:2020|max:2030',
            'codigo_consulta' => 'nullable|string|max:30',
            'page' => 'nullable|integer|min:1',
        ], [
            'fecha_hasta.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
        ]);

        if ($validator->fails()) {
            return back()->with('resultado', [
                'success' => false,
                'error' => 'Filtros inválidos',
                'errors' => $validator->errors(),
            ]);
        }

        try {
            $filtros = [
                'fecha_desde' => $request->fecha_desde,
                'fecha_hasta' => $request->fecha_hasta,
                'entidad' => $request->entidad,
                'estado' => $request->estado,
                'placa' => $request->placa,
                'anio_fiscal' => $request->anio_fiscal,
                'codigo_consulta' => $request->codigo_consulta,
            ];

            $query = $this->service->construirQuery($filtros);

            // Resumen calculado sobre TODO el conjunto filtrado, no solo la página visible.
            $todosFiltrados = (clone $query)->get();
            $resumenGeneral = $this->service->resumenGeneral($todosFiltrados);
            $resumenPorEntidad = $this->service->resumenPorEntidad($todosFiltrados);

            // Variante INTERNA (con id real y comprobante) — panel admin,
            // protegido con sesión + rol, nunca expuesto a bancos.
            $paginador = $query->orderBy('created_at', 'desc')
                ->paginate(20, ['*'], 'page', $request->integer('page', 1))
                ->through(fn($pago) => $this->service->formatearDetalleInterno($pago));

            Log::info('Admin/ReporteConciliacion: Consulta generada', [
                'user' => auth()->user()->email,
                'filtros' => array_filter($filtros),
                'total_registros' => $todosFiltrados->count(),
            ]);

            return back()->with('resultado', [
                'success' => true,
                'filtros_aplicados' => $filtros,
                'resumen_general' => $resumenGeneral,
                'resumen_por_entidad' => $resumenPorEntidad,
                'detalle_pagos' => $paginador->toArray(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Admin/ReporteConciliacion: Error en consulta', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('resultado', [
                'success' => false,
                'error' => 'Ocurrió un error al generar el reporte.',
            ]);
        }
    }
}
