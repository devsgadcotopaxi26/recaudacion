<?php

namespace App\Http\Controllers;

use App\Models\PagoDetalle;
use App\Services\SriVehiculoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;

class VehiculoController extends Controller
{
    private SriVehiculoService $sriService;

    public function __construct(SriVehiculoService $sriService)
    {
        $this->sriService = $sriService;
    }

    /**
     * Mostrar formulario de consulta
     */
    public function consultar()
    {
        return Inertia::render('Consulta/Index');
    }

    /**
     * Buscar vehículo y calcular impuesto usando API del SRI
     */
    public function buscar(Request $request)
    {
        $request->validate([
            'placa' => 'required|string|min:6|max:10',
        ], [
            'placa.required' => 'La placa es obligatoria',
            'placa.min' => 'La placa debe tener al menos 6 caracteres',
            'placa.max' => 'La placa no puede tener más de 10 caracteres',
        ]);

        $placa = strtoupper($request->placa);

        try {
            // Consultar desde el SRI
            $datos = $this->sriService->consultarVehiculoCompleto($placa);

            // Incrementar estadísticas de consultas
            $this->incrementarEstadisticas($placa);

            // Verificar si ya existe un pago completado para este año fiscal
            $anioActual = date('Y');
            $detallePrevio = PagoDetalle::where('placa', $placa)
                ->where('anio_fiscal', $anioActual)
                ->where('estado', 'pagado')
                ->with('transaccionPago')
                ->first();
            $transaccionPrevia = $detallePrevio?->transaccionPago;

            // Retornar con formato correcto para el frontend
            // El frontend espera todos los datos en un solo objeto 'vehiculo'
            return Inertia::render('Consulta/Resultado', [
                'vehiculo' => array_merge(
                    $datos['vehiculo'],  // placa, marca, modelo, anio, clase, cilindraje
                    [
                        'valor_matricula' => $datos['valor_matricula'],
                        'impuesto' => $datos['impuesto'],
                        'total_a_pagar' => $datos['total_a_pagar'],
                        'rubros' => $datos['rubros'],
                        'desglose_anual' => $datos['desglose_anual'],
                    ]
                ),
                'pasarela_habilitada' => config('pagos.pasarela_ciudadana_habilitada'),
                'ya_pagado' => $transaccionPrevia ? true : false,
                'pago_existente' => $transaccionPrevia ? [
                    'id' => $transaccionPrevia->id,
                    'referencia' => $transaccionPrevia->referencia_externa,
                    'certificado_token' => $transaccionPrevia->certificado_token,
                    'fecha' => $transaccionPrevia->fecha_pago,
                    'monto' => $detallePrevio->monto_total,
                ] : null,
                'anio_actual' => $anioActual,
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Incrementar estadísticas de consultas
     */
    private function incrementarEstadisticas(string $placa): void
    {
        try {
            $fecha = date('Y-m-d');
            $hora = date('Y-m-d:H');

            // Incrementar contadores
            Redis::incr('stats:consultas:total');
            Redis::incr("stats:consultas:dia:{$fecha}");
            Redis::incr("stats:consultas:hora:{$hora}");

            // Ranking de placas más consultadas
            Redis::zincrby('stats:placas:populares', 1, $placa);

            // Expirar contadores de hora después de 48 horas
            Redis::expire("stats:consultas:hora:{$hora}", 172800);
        } catch (\Exception $e) {
            // No detener el flujo si falla el registro de estadísticas
            \Log::warning('Error al incrementar estadísticas: ' . $e->getMessage());
        }
    }
}
