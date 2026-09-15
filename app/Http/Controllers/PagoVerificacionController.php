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
     * Mostrar página de verificación de pagos (comprobante + QR, en un solo
     * lugar: la tarjeta de escaneo QR vive dentro de esta misma pantalla).
     */
    public function index()
    {
        return Inertia::render('Admin/VerificarPago');
    }

    /**
     * Verificar un pago por número de comprobante (PAG-XXXXXX o el id
     * numérico) o por su referencia_pago tal cual — el mismo campo de
     * texto acepta ambos formatos, para cubrir tanto el comprobante
     * impreso/QR (referencia_pago) como el "PAG-XXXXXX" que se usa en el
     * resto del sistema (API bancaria, Reporte de Conciliación).
     */
    public function verificar(Request $request)
    {
        $request->validate([
            'referencia' => 'required|string|max:255',
        ]);

        $busqueda = trim($request->referencia);
        $pago = $this->buscarPago($busqueda);

        if (!$pago) {
            return Inertia::render('Admin/VerificarPago', [
                'error' => 'No se encontró ningún pago con este comprobante o referencia.',
                'referenciaBuscada' => $busqueda,
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
                'comprobante' => 'PAG-' . str_pad($pago->id, 6, '0', STR_PAD_LEFT),
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

    /**
     * Busca un Pago admitiendo dos formatos en el mismo campo de texto:
     *  - "PAG-XXXXXX" o un número puro  → se interpreta como el id del pago.
     *  - cualquier otro texto           → se busca tal cual en referencia_pago
     *    (el dato que codifica el QR del comprobante).
     *
     * Se prioriza la coincidencia exacta por referencia_pago (es el
     * identificador "fuerte" del pago); si no hay match, se intenta como id.
     */
    private function buscarPago(string $busqueda): ?Pago
    {
        $pago = Pago::where('referencia_pago', $busqueda)->first();

        if (!$pago && preg_match('/^(?:PAG-)?0*([0-9]+)$/i', $busqueda, $matches) && $matches[1] !== '') {
            $pago = Pago::find((int) $matches[1]);
        }

        return $pago;
    }
}
