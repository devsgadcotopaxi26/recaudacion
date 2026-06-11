<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Vehiculo;
use App\Services\PaymentGatewayService;
use App\Services\SriVehiculoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class PagoController extends Controller
{
    public function __construct(
        private PaymentGatewayService $paymentService,
        private SriVehiculoService $sriService
    ) {
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
        $pagoPrevio = Pago::where('placa', $placa)
            ->where('anio_fiscal', date('Y'))
            ->where('estado', 'pagado')
            ->first();

        if ($pagoPrevio) {
            return redirect()->route('pago.comprobante', $pagoPrevio->id)
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

            // Verificar si ya existe un pago pendiente
            $pagoExistente = Pago::where('placa', $placa)
                ->where('anio_fiscal', date('Y'))
                ->where('estado', 'pendiente')
                ->first();

            if ($pagoExistente && $pagoExistente->link_pago) {
                \Log::info('PagoController::procesar - Pago existente con link');
                return Inertia::render('Pago/Procesar', [
                    'link_pago' => $pagoExistente->link_pago,
                    'pago' => $pagoExistente
                ]);
            }

            // Crear nuevo pago con datos de facturación
            \Log::info('PagoController::procesar - Creando pago');

            $pago = Pago::create([
                'vehiculo_id' => null,
                'placa' => $placa,
                'monto_impuesto' => $impuestoReal, // Usar valor recalculado
                'monto_total' => $impuestoReal, // Usar valor recalculado
                'estado' => 'pendiente',
                'anio_fiscal' => date('Y'),
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

            \Log::info('PagoController::procesar - Pago creado', ['pago_id' => $pago->id]);

            // Generar link de pago
            $resultado = $this->paymentService->generarLinkPago($pago);

            \Log::info('PagoController::procesar - Resultado', ['success' => $resultado['success']]);

            if (!$resultado['success']) {
                \Log::error('PagoController::procesar - Error', ['mensaje' => $resultado['message']]);
                return redirect()->back()->with('error', $resultado['message'] ?? 'Error al generar el link de pago');
            }

            // Renderizar página de procesamiento
            return Inertia::render('Pago/Procesar', [
                'link_pago' => $resultado['link_pago'],
                'pago' => $pago
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
     * Método iniciar eliminado - reemplazado por facturacion() y procesar()
     */

    /**
     * Iniciar proceso de pago con datos del SRI
     */
    public function iniciar(Request $request)
    {
        \Log::info('PagoController::iniciar - Inicio', ['request' => $request->all()]);

        $request->validate([
            'placa' => 'required|string|max:10',
            'valor_matricula' => 'required|numeric|min:0',
            'impuesto' => 'required|numeric|min:0',
        ]);

        \Log::info('PagoController::iniciar - Validación exitosa');

        $placa = strtoupper($request->placa);

        try {
            // Verificar si ya existe un pago pendiente para este vehículo este año
            $pagoExistente = Pago::where('placa', $placa)
                ->where('anio_fiscal', date('Y'))
                ->where('estado', 'pendiente')
                ->first();

            \Log::info('PagoController::iniciar - Pago existente', ['existe' => $pagoExistente ? 'sí' : 'no']);

            // Si ya tiene link de pago, redirigir directamente
            if ($pagoExistente && $pagoExistente->link_pago) {
                \Log::info('PagoController::iniciar - Redirigiendo a pago existente');
                return Inertia::render('Pago/Procesar', [
                    'link_pago' => $pagoExistente->link_pago,
                    'pago' => $pagoExistente
                ]);
            }

            // Crear nuevo registro de pago (sin vehiculo_id porque no tenemos modelo)
            \Log::info('PagoController::iniciar - Creando nuevo pago');

            $pago = Pago::create([
                'vehiculo_id' => null, // Ya no usamos ID de vehículo
                'placa' => $placa,
                'monto_impuesto' => floatval($request->impuesto),
                'monto_total' => floatval($request->impuesto),
                'estado' => 'pendiente',
                'anio_fiscal' => date('Y'),
            ]);

            \Log::info('PagoController::iniciar - Pago creado', ['pago_id' => $pago->id]);

            // Generar link de pago en la pasarela
            \Log::info('PagoController::iniciar - Generando link de pago');

            $resultado = $this->paymentService->generarLinkPago($pago);

            \Log::info('PagoController::iniciar - Resultado del servicio', ['resultado' => $resultado]);

            if (!$resultado['success']) {
                \Log::error('PagoController::iniciar - Error en generación de link', ['mensaje' => $resultado['message'] ?? 'Sin mensaje']);
                return redirect()->back()->with('error', $resultado['message'] ?? 'Error al generar el link de pago');
            }

            // Renderizar página de procesamiento
            \Log::info('PagoController::iniciar - Renderizando página Pago/Procesar');

            return Inertia::render('Pago/Procesar', [
                'link_pago' => $resultado['link_pago'],
                'pago' => $pago
            ]);

        } catch (\Exception $e) {
            \Log::error('PagoController::iniciar - Excepción capturada', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error al iniciar el pago: ' . $e->getMessage());
        }
    }

    /**
     * Callback cuando el usuario vuelve de la pasarela
     */
    public function callback(Request $request)
    {
        $pagoId = $request->query('pago_id');
        $referencia = $request->query('referencia');

        if (!$pagoId) {
            return redirect()->route('home')->with('error', 'No se pudo verificar el pago');
        }

        $pago = Pago::find($pagoId);

        if (!$pago) {
            return redirect()->route('home')->with('error', 'Pago no encontrado');
        }

        // En modo de prueba, marcar como pagado automáticamente
        if ($request->query('test') === '1') {
            $pago->marcarComoPagado('TEST-' . time());
        }

        // Obtener datos del vehículo desde el SRI
        try {
            $datosVehiculo = $this->sriService->obtenerDetalleCompleto($pago->placa);
        } catch (\Exception $e) {
            $datosVehiculo = ['numeroPlaca' => $pago->placa];
        }

        return Inertia::render('Pago/Confirmacion', [
            'pago' => $pago,
            'vehiculo' => $datosVehiculo
        ]);
    }

    /**
     * Mostrar confirmación de pago
     */
    public function confirmacion(Pago $pago)
    {
        // Obtener datos del vehículo desde el SRI
        try {
            $datosVehiculo = $this->sriService->obtenerDetalleCompleto($pago->placa);
        } catch (\Exception $e) {
            $datosVehiculo = ['numeroPlaca' => $pago->placa];
        }

        return Inertia::render('Pago/Confirmacion', [
            'pago' => $pago,
            'vehiculo' => $datosVehiculo
        ]);
    }

    /**
     * Descargar comprobante de pago
     */
    public function comprobante(Pago $pago)
    {
        if (!$pago->estaPagado()) {
            return redirect()->route('home')
                ->with('error', 'El comprobante solo está disponible para pagos completados');
        }

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
                'pago' => $pago,
                'vehiculo' => $vehiculo
            ];
        });

        return Inertia::render('Pago/Comprobante', $datos);
    }

    /**
     * Verificar autenticidad de un comprobante (para QR code)
     */
    public function verificar(string $referencia)
    {
        try {
            // Buscar pago por referencia
            $pago = Pago::where('referencia_pago', $referencia)->first();

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
                    'referencia' => $pago->referencia_pago,
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
