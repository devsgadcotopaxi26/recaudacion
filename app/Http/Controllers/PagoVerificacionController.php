<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Services\SriVehiculoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PagoVerificacionController extends Controller
{
    public function __construct(
        private SriVehiculoService $sriService
    ) {
    }

    /**
     * Mostrar página de verificación de pagos
     */
    public function index()
    {
        return Inertia::render('Admin/VerificarPago');
    }

    /**
     * Verificar un pago por su referencia
     */
    public function verificar(Request $request)
    {
        $request->validate([
            'referencia' => 'required|string|max:255',
        ]);

        $referencia = $request->referencia;

        // Buscar pago por referencia
        $pago = Pago::where('referencia_pago', $referencia)->first();

        if (!$pago) {
            return Inertia::render('Admin/VerificarPago', [
                'error' => 'No se encontró ningún pago con esta referencia.',
                'referenciaBuscada' => $referencia,
            ]);
        }

        // Obtener datos del vehículo desde el SRI
        $datosVehiculo = null;
        if ($pago->placa) {
            try {
                $datosVehiculo = $this->sriService->obtenerDetalleCompleto($pago->placa);
            } catch (\Exception $e) {
                // Si falla la consulta al SRI, usar datos mínimos
                $datosVehiculo = [
                    'placa' => $pago->placa,
                    'marca' => 'N/A',
                    'modelo' => 'N/A',
                    'anioModelo' => 'N/A',
                ];
            }
        }

        return Inertia::render('Admin/VerificarPago', [
            'pagoEncontrado' => [
                'id' => $pago->id,
                'referencia' => $pago->referencia_pago,
                'placa' => $pago->placa,
                'monto_impuesto' => floatval($pago->monto_impuesto),
                'monto_total' => floatval($pago->monto_total),
                'estado' => $pago->estado,
                'fecha_pago' => $pago->fecha_pago?->format('d/m/Y H:i:s'),
                'anio_fiscal' => $pago->anio_fiscal,
                'datos_facturacion' => $pago->datos_facturacion,
                'vehiculo' => $datosVehiculo ? [
                    'placa' => $datosVehiculo['placa'] ?? $pago->placa,
                    'marca' => $datosVehiculo['marca'] ?? 'N/A',
                    'modelo' => $datosVehiculo['modelo'] ?? 'N/A',
                    'anio' => $datosVehiculo['anioModelo'] ?? 'N/A',
                ] : null,
            ],
        ]);
    }
}
