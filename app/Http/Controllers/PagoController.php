<?php

namespace App\Http\Controllers;

use App\Models\TransaccionPago;
use App\Models\PagoDetalle;
use App\Models\Vehiculo;
use App\Services\PaymentGatewayService;
use App\Services\SriVehiculoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PagoController extends Controller
{
    public function __construct(
        private PaymentGatewayService $paymentService,
        private SriVehiculoService $sriService
    ) {
    }

    /**
     * Aplana una TransaccionPago (+ su único detalle, siempre exactamente 1
     * año en el flujo de pasarela ciudadana) a la misma forma plana que
     * antes tenía un Pago individual — para que las vistas Pago/*.vue
     * (Procesar, Confirmacion, Comprobante, Certificado, Verificacion) no
     * necesiten cambiar ni un campo.
     */
    private function aplanarTransaccion(TransaccionPago $transaccion): array
    {
        $detalle = $transaccion->detalles->first();

        return [
            'id' => $transaccion->id,
            'placa' => $transaccion->placa,
            'referencia_pago' => $transaccion->referencia_externa,
            'certificado_token' => $transaccion->certificado_token,
            'link_pago' => $transaccion->link_pago,
            'estado' => $transaccion->estado,
            'fecha_pago' => $transaccion->fecha_pago,
            'anio_fiscal' => $detalle?->anio_fiscal,
            'monto_impuesto' => $detalle?->monto_impuesto,
            'monto_total' => $transaccion->monto_total,
            'datos_facturacion' => $transaccion->datos_facturacion,
            'created_at' => $transaccion->created_at,
        ];
    }

    /**
     * Mostrar formulario de datos de facturación
     */
    public function facturacion(Request $request)
    {
        $request->validate([
            'placa' => 'required|string|max:10',
            'valor_matricula' => 'required|numeric|min:0',
            'impuesto' => 'required|numeric|min:0',
        ]);

        $placa = strtoupper($request->placa);

        // Verificar si ya existe pago completado este año
        $detallePrevio = PagoDetalle::where('placa', $placa)
            ->where('anio_fiscal', date('Y'))
            ->where('estado', 'pagado')
            ->first();

        if ($detallePrevio) {
            return redirect()->route('pago.comprobante', $detallePrevio->transaccion_pago_id)
                ->with('info', 'Este vehículo ya pagó el impuesto este año.');
        }

        return Inertia::render('Pago/DatosFacturacion', [
            'placa' => $placa,
            'valor_matricula' => floatval($request->valor_matricula),
            'impuesto' => floatval($request->impuesto),
        ]);
    }

    /**
     * Procesar datos de facturación y generar link de pago
     */
    public function procesar(Request $request)
    {
        \Log::info('PagoController::procesar - Inicio', ['request' => $request->all()]);

        $request->validate([
            'placa' => 'required|string|max:10',
            'tipo_documento' => 'required|in:cedula,ruc',
            'documento' => 'required|string|min:10|max:13',
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telefono' => 'required|string|min:7|max:15',
            'direccion' => 'required|string|max:500',
            'acepta_proteccion_datos' => 'required|accepted', // LOPDP Ecuador
        ], [
            'acepta_proteccion_datos.required' => 'Debe aceptar la Política de Protección de Datos.',
            'acepta_proteccion_datos.accepted' => 'Debe aceptar la Política de Protección de Datos para continuar.',
        ]);


        $placa = strtoupper($request->placa);

        try {
            // IMPORTANTE: NO confiar en el impuesto que viene del request
            // Recalcular desde el SRI para evitar manipulación
            \Log::info('PagoController::procesar - Recalculando impuesto desde SRI');

            $datosVehiculo = $this->sriService->consultarVehiculoCompleto($placa);
            $impuestoReal = $datosVehiculo['impuesto'];

            \Log::info('PagoController::procesar - Impuesto recalculado', [
                'impuesto' => $impuestoReal
            ]);

            // Verificar si ya existe un pago pendiente (año actual, vía su
            // único detalle — la pasarela ciudadana siempre paga 1 año)
            $transaccionExistente = TransaccionPago::whereHas('detalles', function ($q) {
                    $q->where('anio_fiscal', date('Y'));
                })
                ->where('placa', $placa)
                ->where('estado', 'pendiente')
                ->first();

            if ($transaccionExistente && $transaccionExistente->link_pago) {
                \Log::info('PagoController::procesar - Pago existente con link');
                return Inertia::render('Pago/Procesar', [
                    'link_pago' => $transaccionExistente->link_pago,
                    'pago' => $this->aplanarTransaccion($transaccionExistente),
                ]);
            }

            // Crear nueva transacción (cabecera + 1 detalle: la pasarela
            // ciudadana siempre paga exactamente el año en curso) con datos
            // de facturación.
            \Log::info('PagoController::procesar - Creando pago');

            $transaccion = DB::transaction(function () use ($placa, $impuestoReal, $request) {
                $transaccion = TransaccionPago::create([
                    'placa' => $placa,
                    'canal' => 'pasarela_ciudadana',
                    'monto_total' => $impuestoReal, // Usar valor recalculado
                    'estado' => 'pendiente',
                    'datos_facturacion' => [
                        'tipo_documento' => $request->tipo_documento,
                        'documento' => $request->documento,
                        'nombre' => $request->nombre,
                        'email' => $request->email,
                        'telefono' => $request->telefono,
                        'direccion' => $request->direccion,
                        // LOPDP Ecuador - Registro de consentimiento
                        'consentimiento_proteccion_datos' => true,
                        'consentimiento_fecha' => now()->toDateTimeString(),
                        'consentimiento_ip' => $request->ip(),
                    ],
                ]);

                $transaccion->detalles()->create([
                    'placa' => $placa,
                    'estado' => 'pendiente',
                    'anio_fiscal' => date('Y'),
                    'monto_impuesto' => $impuestoReal,
                    'monto_total' => $impuestoReal,
                ]);

                return $transaccion;
            });

            \Log::info('PagoController::procesar - Pago creado', ['transaccion_pago_id' => $transaccion->id]);

            // Generar link de pago
            $resultado = $this->paymentService->generarLinkPago($transaccion);

            \Log::info('PagoController::procesar - Resultado', ['success' => $resultado['success']]);

            if (!$resultado['success']) {
                \Log::error('PagoController::procesar - Error', ['mensaje' => $resultado['message']]);
                return redirect()->back()->with('error', $resultado['message'] ?? 'Error al generar el link de pago');
            }

            // Renderizar página de procesamiento
            return Inertia::render('Pago/Procesar', [
                'link_pago' => $resultado['link_pago'],
                'pago' => $this->aplanarTransaccion($transaccion->fresh('detalles')),
            ]);

        } catch (\Exception $e) {
            \Log::error('PagoController::procesar - Excepción', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error al procesar el pago: ' . $e->getMessage());
        }
    }

    /**
     * Callback cuando el usuario vuelve de la pasarela
     */
    public function callback(Request $request)
    {
        $pagoId = $request->query('pago_id');

        if (!$pagoId) {
            return redirect()->route('home')->with('error', 'No se pudo verificar el pago');
        }

        $transaccion = TransaccionPago::with('detalles')->find($pagoId);

        if (!$transaccion) {
            return redirect()->route('home')->with('error', 'Pago no encontrado');
        }

        // En modo de prueba, marcar como pagado automáticamente
        if ($request->query('test') === '1') {
            $transaccion->marcarComoPagado('TEST-' . time());
        }

        // Obtener datos del vehículo desde el SRI
        try {
            $datosVehiculo = $this->sriService->obtenerDetalleCompleto($transaccion->placa);
        } catch (\Exception $e) {
            $datosVehiculo = ['numeroPlaca' => $transaccion->placa];
        }

        return Inertia::render('Pago/Confirmacion', [
            'pago' => $this->aplanarTransaccion($transaccion->fresh('detalles')),
            'vehiculo' => $datosVehiculo
        ]);
    }

    /**
     * Mostrar confirmación de pago
     */
    public function confirmacion(TransaccionPago $pago)
    {
        $pago->loadMissing('detalles');

        // Obtener datos del vehículo desde el SRI
        try {
            $datosVehiculo = $this->sriService->obtenerDetalleCompleto($pago->placa);
        } catch (\Exception $e) {
            $datosVehiculo = ['numeroPlaca' => $pago->placa];
        }

        return Inertia::render('Pago/Confirmacion', [
            'pago' => $this->aplanarTransaccion($pago),
            'vehiculo' => $datosVehiculo
        ]);
    }

    /**
     * Descargar comprobante de pago
     */
    public function comprobante(TransaccionPago $pago)
    {
        if (!$pago->estaPagado()) {
            return redirect()->route('home')
                ->with('error', 'El comprobante solo está disponible para pagos completados');
        }

        $pago->loadMissing('detalles');

        // Cachear datos del comprobante (30 días)
        $cacheKey = "comprobante:pago:{$pago->id}";

        $datos = Cache::remember($cacheKey, 2592000, function () use ($pago) {
            // Obtener datos del vehículo desde el SRI usando la placa
            try {
                $datosVehiculo = $this->sriService->obtenerDetalleCompleto($pago->placa);

                // Extraer solo los datos necesarios
                $vehiculo = [
                    'placa' => $datosVehiculo['placa'] ?? $pago->placa,
                    'marca' => $datosVehiculo['marca'] ?? '',
                    'modelo' => $datosVehiculo['modelo'] ?? '',
                    'anio' => $datosVehiculo['anioModelo'] ?? '',
                ];
            } catch (\Exception $e) {
                // Si falla el SRI, usar valores mínimos
                $vehiculo = [
                    'placa' => $pago->placa,
                    'marca' => '',
                    'modelo' => '',
                    'anio' => '',
                ];
            }

            return [
                'pago' => $this->aplanarTransaccion($pago),
                'vehiculo' => $vehiculo
            ];
        });

        return Inertia::render('Pago/Comprobante', $datos);
    }

    /**
     * Certificado de Pago: documento estandarizado, valido sin importar la
     * entidad recaudadora donde se pago. Acceso publico via certificado_token
     * (no referencia_pago, que define el banco/cooperativa externo).
     */
    public function certificado(string $token)
    {
        $pago = TransaccionPago::where('certificado_token', $token)->first();

        if (!$pago || !$pago->estaPagado()) {
            return redirect()->route('vehiculos.consultar')
                ->with('error', 'Certificado no encontrado o el pago no está completado.');
        }

        $pago->loadMissing(['detalles', 'apiToken']);

        $cacheKey = "certificado:pago:{$pago->id}";

        $datos = Cache::remember($cacheKey, 2592000, function () use ($pago) {
            try {
                $datosVehiculo = $this->sriService->obtenerDetalleCompleto($pago->placa);

                $vehiculo = [
                    'placa' => $datosVehiculo['placa'] ?? $pago->placa,
                    'marca' => $datosVehiculo['marca'] ?? '',
                    'modelo' => $datosVehiculo['modelo'] ?? '',
                    'anio' => $datosVehiculo['anioModelo'] ?? '',
                ];
            } catch (\Exception $e) {
                $vehiculo = [
                    'placa' => $pago->placa,
                    'marca' => '',
                    'modelo' => '',
                    'anio' => '',
                ];
            }

            return [
                // Certificado = una transacción, con su desglose de años
                // adentro (uno solo en el flujo de pasarela ciudadana, hasta
                // varios en un pago bancario consolidado).
                'pago' => $this->aplanarTransaccion($pago),
                'detalles' => $pago->detalles->map(fn($d) => [
                    'anio_fiscal' => $d->anio_fiscal,
                    'monto_impuesto' => (float) $d->monto_impuesto,
                    'monto_mora' => (float) $d->monto_mora,
                    'monto_total' => (float) $d->monto_total,
                ])->values(),
                'vehiculo' => $vehiculo,
                'entidad_recaudadora' => $pago->nombreEntidad(),
            ];
        });

        return Inertia::render('Pago/Certificado', $datos);
    }

    /**
     * Verificar autenticidad de un comprobante (para QR code)
     */
    public function verificar(string $referencia)
    {
        try {
            // Buscar pago por referencia
            $pago = TransaccionPago::where('referencia_externa', $referencia)->first();

            if (!$pago) {
                return Inertia::render('Pago/Verificacion', [
                    'valido' => false,
                    'mensaje' => 'Comprobante no encontrado. La referencia no existe en el sistema.',
                    'referencia' => $referencia
                ]);
            }

            // Verificar que el pago esté completado
            if ($pago->estado !== 'pagado') {
                return Inertia::render('Pago/Verificacion', [
                    'valido' => false,
                    'mensaje' => 'El pago asociado a esta referencia no ha sido completado.',
                    'referencia' => $referencia,
                    'estado' => $pago->estado
                ]);
            }

            // Pago válido
            return Inertia::render('Pago/Verificacion', [
                'valido' => true,
                'mensaje' => '✓ Comprobante Auténtico Verificado',
                'pago' => [
                    'id' => $pago->id,
                    'referencia' => $pago->referencia_externa,
                    'placa' => $pago->placa,
                    'monto' => $pago->monto_total,
                    'fecha' => $pago->fecha_pago,
                    'estado' => $pago->estado,
                ]
            ]);

        } catch (\Exception $e) {
            return Inertia::render('Pago/Verificacion', [
                'valido' => false,
                'mensaje' => 'Error al verificar el comprobante. Por favor, intente nuevamente.',
                'referencia' => $referencia
            ]);
        }
    }
}
