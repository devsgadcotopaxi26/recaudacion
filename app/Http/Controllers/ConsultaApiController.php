<?php

namespace App\Http\Controllers;

use App\Services\DeudaVehicularService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ConsultaApiController extends Controller
{
    public function __construct(private DeudaVehicularService $deudaService)
    {
    }

    /**
     * Mostrar página de consulta visual de deuda vehicular
     */
    public function index()
    {
        return Inertia::render('Admin/ConsultaApi');
    }

    /**
     * Ejecutar la consulta de deuda por placa.
     *
     * Reutiliza DeudaVehicularService — la misma lógica (SRI + cruce con
     * pagos locales) que usa BancaController@consultarDeuda — para que esta
     * pantalla refleje correctamente qué años ya están pagados, en vez de
     * mostrar solo el bruto del SRI.
     */
    public function consultar(Request $request)
    {
        $request->validate([
            'placa' => 'required|string|max:10',
        ], [
            'placa.required' => 'La placa es obligatoria.',
            'placa.max'      => 'La placa no puede exceder 10 caracteres.',
        ]);

        $placa = strtoupper($request->placa);

        try {
            $resultado = $this->deudaService->consultar($placa);

            Log::info('Admin/ConsultaApi: Consulta exitosa', [
                'placa' => $placa,
                'user'  => auth()->user()->email,
                'todos_pagados' => $resultado['todos_pagados'],
            ]);

            return back()->with('resultado', [
                'success'            => true,
                'placa'              => $placa,
                'vehiculo'           => $resultado['vehiculo'],
                'valor_matricula'    => $resultado['valor_matricula'],
                'todos_pagados'      => $resultado['todos_pagados'],
                'desglose_anual'     => $resultado['desglose_anual'],
                'totales_brutos'     => $resultado['totales_sri'],
                'totales_pendientes' => $resultado['totales_pendientes'],
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
