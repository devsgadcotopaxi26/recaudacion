<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BancaController extends Controller
{
    /**
     * Consultar deuda de un vehículo por placa usando API del SRI
     * 
     * POST /api/v1/consulta-deuda
     */
    public function consultarDeuda(Request $request)
    {
        // Validar request
        $validator = Validator::make($request->all(), [
            'placa' => 'required|string|max:10',
            'anio_fiscal' => 'nullable|integer|min:2020|max:2030',
        ], [
            'placa.required' => 'La placa es obligatoria',
            'placa.max' => 'La placa no puede exceder los 10 caracteres',
            'placa.string' => 'El formato de la placa es inválido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $placa = strtoupper($request->placa);
        $anioFiscal = $request->anio_fiscal ?? date('Y');

        try {
            // Consultar al SRI (mismo flujo que la web)
            $sriService = new \App\Services\SriVehiculoService();
            $datos = $sriService->consultarVehiculoCompleto($placa);

            // Verificar si ya pagó para este año (mismo flujo que VehiculoController)
            // Nota: Aquí validamos si EXISTE RECAUDACIÓN LOCAL para evitar duplicados
            $pagoPrevio = Pago::where('placa', $placa)
                ->where('anio_fiscal', $anioFiscal)
                ->where('estado', 'pagado')
                ->first();

            if ($pagoPrevio) {
                return response()->json([
                    'success' => false,
                    'message' => 'El vehículo no tiene deuda pendiente para este año',
                    'pago_existente' => [
                        'fecha_pago' => $pagoPrevio->fecha_pago,
                        'referencia' => $pagoPrevio->referencia_pago,
                        'monto' => $pagoPrevio->monto_total
                    ]
                ], 404);
            }

            $vehiculo = $datos['vehiculo'];

            // Registrar la consulta en la base de datos
            // ── Guardia: no grabar si el resultado es incoherente (deuda con $0) ──
            // Esto evita registros espurios de auditoría cuando el sistema está en
            // un estado transitorio (SRI sin deuda pero historial aún no consultado).
            $metodoSri    = $datos['metodo_sri'] ?? 'desconocido';
            $totalAPagar  = $datos['totales']['total_a_pagar'] ?? 0;
            $esIncoherente = ($metodoSri === 'deuda' && $totalAPagar <= 0);

            if (!$esIncoherente) {
                try {
                    \App\Models\ConsultaBancaria::create([
                        'api_token_id'   => $request->api_token_id,
                        'entidad_nombre' => $request->entidad_nombre,
                        'placa'          => $placa,
                        'anio_fiscal'    => $anioFiscal,
                        'metodo_sri'     => $metodoSri,
                        'valor_matricula'=> $datos['valor_matricula'] ?? 0,
                        'total_rodaje'   => $datos['totales']['total_rodaje'] ?? 0,
                        'total_mora'     => $datos['totales']['total_mora'] ?? 0,
                        'total_a_pagar'  => $totalAPagar,
                        'ip_address'     => request()->ip(),
                        'user_agent'     => request()->userAgent(),
                    ]);
                } catch (\Exception $e) {
                    Log::warning('API: No se pudo registrar el log de consulta', ['error' => $e->getMessage()]);
                }
            } else {
                Log::info('API: Registro de auditoría omitido (resultado incoherente: deuda con $0)', [
                    'placa'      => $placa,
                    'metodo_sri' => $metodoSri,
                    'total'      => $totalAPagar,
                ]);
            }

            Log::info('API: Consulta de deuda exitosa', [
                'placa' => $placa,
                'anio_fiscal' => $anioFiscal,
                'total_a_pagar' => $datos['totales']['total_a_pagar'],
                'metodo' => $datos['metodo_sri'] ?? 'deuda'
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'placa' => $vehiculo['placa'],
                    'vehiculo' => [
                        'marca' => $vehiculo['marca'],
                        'modelo' => $vehiculo['modelo'],
                        'anio' => $vehiculo['anio'],
                        'tipo' => $vehiculo['clase'] ?? 'automovil',
                        'descripcion' => $vehiculo['descripcion_completa'] ?? '',
                    ],
                    'valor_matricula' => $datos['valor_matricula'],
                    'desglose_anual' => $datos['desglose_anual'],
                    'totales' => $datos['totales'],
                ]
            ], 200);

        } catch (\Throwable $e) {
            try {
                Log::error('API: Error al consultar SRI', [
                    'placa' => $placa,
                    'error' => $e->getMessage(),
                    'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : 'Log trace disabled in production'
                ]);
            } catch (\Throwable $logError) {
                // Si el log falla, no hacemos nada para evitar el crash 500
            }

            $status = $e->getCode();
            // Si el código no es un status HTTP válido, usar 500
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            $message = 'No se pudo consultar la información del vehículo';
            $error = $e->getMessage();

            // Personalizar mensaje para errores 500 (errores internos o del sistema)
            if ($status >= 500) {
                $message = 'Ocurrió un error interno al consultar la información';
                if (!app()->environment('local')) {
                    $error = 'Servicio temporalmente no disponible, intente más tarde';
                }
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => $error
            ], $status);
        }
    }

    /**
     * Registrar un pago realizado por entidad bancaria
     * 
     * POST /api/v1/registrar-pago
     */
    public function registrarPago(Request $request)
    {
        // Validar request
        $validator = Validator::make($request->all(), [
            'placa' => 'required|string|max:10',
            'anio_fiscal' => 'required|integer|min:2020|max:2030',
            'monto' => 'required|numeric|min:0.01',
            'referencia_externa' => 'required|string|max:100',
            'fecha_pago' => 'required|date',
            'entidad_recaudadora' => 'required|string|max:100',
        ], [
            'placa.required' => 'La placa es obligatoria',
            'placa.max' => 'La placa no puede exceder los 10 caracteres',
            'placa.string' => 'El formato de la placa es inválido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $placa = strtoupper($request->placa);
        $anioFiscal = $request->anio_fiscal;
        $monto = $request->monto;

        try {
            // Verificar si ya existe un pago para este año
            $pagoExistente = Pago::where('placa', $placa)
                ->where('anio_fiscal', $anioFiscal)
                ->where('estado', 'pagado')
                ->first();

            if ($pagoExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'El vehículo ya tiene el impuesto pagado para este año',
                    'pago_existente' => [
                        'id' => $pagoExistente->id,
                        'fecha_pago' => $pagoExistente->fecha_pago,
                        'referencia' => $pagoExistente->referencia_pago,
                        'monto' => $pagoExistente->monto_total
                    ]
                ], 400);
            }

            // Consultar SRI para validar monto
            $sriService = new \App\Services\SriVehiculoService();
            $datos = $sriService->consultarVehiculoCompleto($placa);
            $montoCalculado = $datos['impuesto'];

            // Verificar que el monto sea correcto (tolerancia de $1.00)
            $diferencia = abs($monto - $montoCalculado);

            if ($diferencia > 1.00) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto enviado no coincide con el impuesto calculado',
                    'monto_enviado' => round($monto, 2),
                    'monto_esperado' => round($montoCalculado, 2),
                    'diferencia' => round($diferencia, 2)
                ], 400);
            }

            // Crear registro de pago
            $pago = Pago::create([
                'placa' => $placa,
                'anio_fiscal' => $anioFiscal,
                'monto_impuesto' => $montoCalculado,
                'monto_total' => $monto,
                'estado' => 'pagado',
                'referencia_pago' => $request->referencia_externa,
                'fecha_pago' => $request->fecha_pago,
                'datos_adicionales' => [
                    'metodo_pago' => 'API_Bancaria',
                    'entidad_recaudadora' => $request->entidad_recaudadora,
                    'vehiculo' => $datos['vehiculo']
                ],
            ]);

            Log::info('API: Pago registrado exitosamente', [
                'pago_id' => $pago->id,
                'placa' => $placa,
                'monto' => $monto,
                'entidad' => $request->entidad_recaudadora,
                'referencia' => $request->referencia_externa
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'data' => [
                    'pago_id' => $pago->id,
                    'comprobante' => 'PAG-' . str_pad($pago->id, 6, '0', STR_PAD_LEFT),
                    'fecha_registro' => $pago->created_at->format('Y-m-d H:i:s'),
                    'monto_registrado' => round((float) $pago->monto_total, 2),
                    'placa' => $placa,
                    'anio_fiscal' => $anioFiscal
                ]
            ], 201);

        } catch (\Throwable $e) {
            try {
                Log::error('API: Error al registrar pago', [
                    'error' => $e->getMessage(),
                    'placa' => $placa,
                    'monto' => $monto,
                    'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : 'Log trace disabled in production'
                ]);
            } catch (\Throwable $logError) {
                // Silencio si falla el logger
            }

            $status = $e->getCode();
            // Si el código no es un status HTTP válido, usar 500
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            $message = 'Error al registrar el pago';
            $error = $e->getMessage();

            // Personalizar mensaje para errores 500 (errores internos o del sistema)
            if ($status >= 500) {
                $message = 'Ocurrió un error inesperado al procesar el pago';
                if (!app()->environment('local')) {
                    $error = 'No se pudo completar el registro, intente más tarde';
                }
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => $error
            ], $status);
        }
    }

    /**
     * Simulador de cálculo de rodaje y mora (solo para pruebas)
     *
     * Reglas de negocio aplicadas:
     *  - Rodaje = 10% del subtotal por año (sin tope por año)
     *  - Total rodaje (suma de todos los años) se ajusta: mín $10, máx $100
     *  - El rodaje ajustado se distribuye proporcionalmente entre los años
     *  - Mora por año = 10% del rodaje crudo × años de atraso (sin tope)
     *
     * POST /api/v1/test/simulacion
     */
    public function simulacion(Request $request)
    {
        $anioActual = intval(date('Y'));

        $validator = Validator::make($request->all(), [
            'valor_matricula_anual' => 'required|numeric|min:1',
            'anio_inicio' => 'required|integer|min:2010|max:' . $anioActual,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $valorAnual = floatval($request->valor_matricula_anual);
        $anioInicio = intval($request->anio_inicio);
        $totalFicticio = 0;

        // Generar datos ficticios del SRI con la estructura real
        $detallesRubro = [];
        for ($anio = $anioInicio; $anio <= $anioActual; $anio++) {
            $totalFicticio += $valorAnual;

            // Simular distribución base: 50% TASA SPPAT, 30% IMPUESTO, 20% TASA ANT
            $detallesRubro[] = [
                'descripcion' => 'TASA',
                'anio' => $anio,
                'valor' => round($valorAnual * 0.50, 4),
            ];
            $detallesRubro[] = [
                'descripcion' => 'IMPUESTO',
                'anio' => $anio,
                'valor' => round($valorAnual * 0.30, 4),
            ];
            $detallesRubro[] = [
                'descripcion' => 'TASA',
                'anio' => $anio,
                'valor' => round($valorAnual * 0.20, 4),
            ];

            // RECARGO e INTERES para años atrasados (incluidos en el cálculo)
            if ($anio < $anioActual) {
                $aniosAtraso = $anioActual - $anio;
                $detallesRubro[] = [
                    'descripcion' => 'RECARGO',
                    'anio' => $anio,
                    'valor' => round($valorAnual * 0.05 * $aniosAtraso, 2),
                ];
                $detallesRubro[] = [
                    'descripcion' => 'INTERES',
                    'anio' => $anio,
                    'valor' => round($valorAnual * 0.02 * $aniosAtraso, 2),
                ];

                // Simular PRESCRIPCION para deudas muy antiguas (ej: más de 5 años)
                if ($aniosAtraso > 5) {
                    $detallesRubro[] = [
                        'descripcion' => 'PRESCRIPCION',
                        'anio' => $anio,
                        'valor' => round($valorAnual * -0.10, 2),
                    ];
                }
            }
        }

        // Construir estructura idéntica a la respuesta del SRI
        $detalleFicticio = [
            'placa' => 'TEST0001',
            'marca' => 'VEHICULO',
            'modelo' => 'DE PRUEBA',
            'anioModelo' => 2020,
            'clase' => 'AUTOMOVIL',
            'cilindraje' => 2000,
            'total' => $totalFicticio,
            'deudas' => [
                [
                    'descripcion' => 'PAGO DEL VALOR DE LA MATRÍCULA',
                    'rubros' => [
                        [
                            'descripcion' => 'RUBROS SIMULADOS',
                            'valor' => $totalFicticio,
                            'periodoFiscal' => $anioInicio . ' - ' . $anioActual,
                            'beneficiario' => 'TEST',
                            'detallesRubro' => $detallesRubro,
                        ]
                    ],
                    'subtotal' => $totalFicticio,
                ]
            ],
        ];

        // Calcular desglose usando la misma lógica real
        $sriService = new \App\Services\SriVehiculoService();
        $desglose = $sriService->calcularDesglosePorAnio($detalleFicticio);

        return response()->json([
            'success' => true,
            'message' => 'Simulación de cálculo (datos ficticios)',
            'parametros' => [
                'valor_matricula_anual' => $valorAnual,
                'anio_inicio' => $anioInicio,
                'anio_actual' => $anioActual,
                'cantidad_anios' => $anioActual - $anioInicio + 1,
            ],
            'data' => [
                'desglose_anual' => $desglose['desglose'],
                'totales' => [
                    'total_rodaje' => $desglose['total_rodaje'],
                    'total_mora' => $desglose['total_mora'],
                    'total_a_pagar' => $desglose['total_a_pagar'],
                ],
            ],
        ], 200);
    }
}
