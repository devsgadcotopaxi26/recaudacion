<?php

namespace App\Http\Controllers;

use App\Models\TransaccionPago;
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
        $transaccion = $this->buscarTransaccion($busqueda);

        if (!$transaccion) {
            return Inertia::render('Admin/VerificarPago', [
                'error' => 'No se encontró ningún pago con este comprobante o referencia.',
                'referenciaBuscada' => $busqueda,
            ]);
        }

        $transaccion->loadMissing('detalles');

        // Obtener datos del vehículo desde el SRI
        $datosVehiculo = null;
        if ($transaccion->placa) {
            try {
                $datosVehiculo = $this->sriService->obtenerDetalleCompleto($transaccion->placa);
            } catch (\Exception $e) {
                // Si falla la consulta al SRI, usar datos mínimos
                $datosVehiculo = [
                    'placa' => $transaccion->placa,
                    'marca' => 'N/A',
                    'modelo' => 'N/A',
                    'anioModelo' => 'N/A',
                ];
            }
        }

        // Un detalle → misma forma de siempre (anio_fiscal/monto_total del
        // único año). Varios detalles → esos mismos campos representan el
        // total/primer año de la transacción, y 'detalles' trae el desglose
        // completo para que la pantalla lo muestre.
        $primerDetalle = $transaccion->detalles->first();

        return Inertia::render('Admin/VerificarPago', [
            'pagoEncontrado' => [
                'id' => $transaccion->id,
                'comprobante' => $transaccion->comprobante(),
                'referencia' => $transaccion->referencia_externa,
                'placa' => $transaccion->placa,
                'monto_impuesto' => floatval($primerDetalle?->monto_impuesto ?? 0),
                'monto_total' => floatval($transaccion->monto_total),
                'estado' => $transaccion->estado,
                'fecha_pago' => $transaccion->fecha_pago?->format('d/m/Y H:i:s'),
                'anio_fiscal' => $primerDetalle?->anio_fiscal,
                'datos_facturacion' => $transaccion->datos_facturacion,
                // Desglose completo de años cubiertos por esta transacción
                // (relevante para pagos bancarios multi-año; un pago de
                // pasarela ciudadana siempre trae exactamente 1).
                'detalles' => $transaccion->detalles->map(fn($d) => [
                    'anio_fiscal' => $d->anio_fiscal,
                    'monto_impuesto' => floatval($d->monto_impuesto),
                    'monto_mora' => floatval($d->monto_mora),
                    'monto_total' => floatval($d->monto_total),
                    'estado' => $d->estado,
                ])->values(),
                'vehiculo' => $datosVehiculo ? [
                    'placa' => $datosVehiculo['placa'] ?? $transaccion->placa,
                    'marca' => $datosVehiculo['marca'] ?? 'N/A',
                    'modelo' => $datosVehiculo['modelo'] ?? 'N/A',
                    'anio' => $datosVehiculo['anioModelo'] ?? 'N/A',
                ] : null,
            ],
        ]);
    }

    /**
     * Busca una TransaccionPago admitiendo dos formatos en el mismo campo
     * de texto:
     *  - "PAG-XXXXXX" o un número puro  → se interpreta como el id.
     *  - cualquier otro texto           → se busca tal cual en referencia_externa
     *    (el dato que codifica el QR del comprobante).
     *
     * Se prioriza la coincidencia exacta por referencia_externa (es el
     * identificador "fuerte" del pago); si no hay match, se intenta como id.
     */
    private function buscarTransaccion(string $busqueda): ?TransaccionPago
    {
        $transaccion = TransaccionPago::where('referencia_externa', $busqueda)->first();

        if (!$transaccion && preg_match('/^(?:PAG-)?0*([0-9]+)$/i', $busqueda, $matches) && $matches[1] !== '') {
            $transaccion = TransaccionPago::find((int) $matches[1]);
        }

        return $transaccion;
    }
}
