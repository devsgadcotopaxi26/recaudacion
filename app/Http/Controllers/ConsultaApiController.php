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

            Log::info('Admin/ConsultaApi: Consulta exitosa', [
                'placa' => $placa,
                'user'  => auth()->user()->email,
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
                'valor_matricula' => $datos['valor_matricula'],
                'desglose_anual'  => $datos['desglose_anual'],
                'totales'         => $datos['totales'],
                'metodo_sri'      => $datos['metodo_sri'] ?? 'deuda',
                'pago_previo'     => null,
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
