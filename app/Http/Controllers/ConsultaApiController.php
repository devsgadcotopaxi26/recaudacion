<?php

namespace App\Http\Controllers;

use App\Services\SriVehiculoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ConsultaApiController extends Controller
{
    /**
     * Mostrar página de consulta visual de la API
     */
    public function index()
    {
        return Inertia::render('Admin/ConsultaApi', [
            'anio_actual' => (int) date('Y'),
        ]);
    }

    /**
     * Ejecutar la consulta (misma lógica que el endpoint bancario)
     */
    public function consultar(Request $request)
    {
        $request->validate([
            'placa' => 'required|string|max:10',
        ], [
            'placa.required' => 'La placa es obligatoria.',
            'placa.max'      => 'La placa no puede exceder 10 caracteres.',
        ]);

        $placa      = strtoupper($request->placa);
        $anioActual = (int) date('Y');

        try {
            $sriService = new SriVehiculoService();
            $datos      = $sriService->consultarVehiculoCompleto($placa);

            // Conciliar el desglose bruto del SRI contra los pagos registrados
            // localmente (misma lógica que usa la API bancaria en
            // BancaController@consultarDeuda, vía SriVehiculoService::conciliarPagosLocales).
            $conciliacion = $sriService->conciliarPagosLocales($placa, $datos['desglose_anual']);

            Log::info('Admin/ConsultaApi: Consulta exitosa', [
                'placa' => $placa,
                'user'  => auth()->user()->email,
                'todos_pagados' => $conciliacion['todos_pagados'],
            ]);

            return back()->with('resultado', [
                'success'      => true,
                'placa'        => $datos['vehiculo']['placa'],
                'vehiculo'     => [
                    'marca'       => $datos['vehiculo']['marca'],
                    'modelo'      => $datos['vehiculo']['modelo'],
                    'anio'        => $datos['vehiculo']['anio'],
                    'tipo'        => $datos['vehiculo']['clase'] ?? 'AUTOMÓVIL',
                    'descripcion' => $datos['vehiculo']['descripcion_completa'] ?? '',
                ],
                'valor_matricula'    => $datos['valor_matricula'],
                // Desglose ya conciliado: cada año trae 'estado' (pagado|pendiente)
                // y, si corresponde, el sub-objeto 'pago' con comprobante/referencia/entidad.
                'desglose_anual'     => $conciliacion['desglose_anual'],
                'todos_pagados'      => $conciliacion['todos_pagados'],
                // Bruto: lo que calcula el sistema a partir de la matrícula del SRI,
                // SIN descontar pagos locales. Solo para fines informativos/auditoría.
                'totales_brutos'     => $datos['totales'],
                // Neto: lo que realmente falta por pagar según la BD local.
                // Este es el valor que debe usarse para decidir si hay deuda.
                'totales_pendientes' => $conciliacion['totales_pendientes'],
                'metodo_sri'         => $datos['metodo_sri'] ?? 'deuda',
            ]);

        } catch (\Throwable $e) {
            Log::warning('Admin/ConsultaApi: Error en consulta', [
                'placa' => $placa,
                'error' => $e->getMessage(),
            ]);

            return back()->with('resultado', [
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
