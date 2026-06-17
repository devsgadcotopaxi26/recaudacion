<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BancaController extends Controller
{
    /**
     * Consultar deuda de un vehículo por placa usando API del SRI
     * 
     * POST /api/v1/consulta-deuda-rodaje-bancos
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
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            $message = 'No se pudo consultar la información del vehículo';
            $error = $e->getMessage();

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

            // ── CAMBIO: ahora guardamos api_token_id para rastrear la entidad ──
            $pago = Pago::create([
                'placa' => $placa,
                'anio_fiscal' => $anioFiscal,
                'monto_impuesto' => $montoCalculado,
                'monto_total' => $monto,
                'estado' => 'pagado',
                'referencia_pago' => $request->referencia_externa,
                'fecha_pago' => $request->fecha_pago,
                'api_token_id' => $request->api_token_id, // ← NUEVO
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
                'referencia' => $request->referencia_externa,
                'api_token_id' => $request->api_token_id, // ← NUEVO
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
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            $message = 'Error al registrar el pago';
            $error = $e->getMessage();

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
     * Verificar el estado de un pago específico
     * 
     * La cooperativa puede consultar si un pago que registró
     * efectivamente quedó guardado en el sistema.
     * 
     * POST /api/v1/verificar-pago
     */
    public function verificarPago(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'referencia_externa' => 'required_without:placa|string|max:100',
            'placa' => 'required_without:referencia_externa|string|max:10',
            'anio_fiscal' => 'nullable|integer|min:2020|max:2030',
        ], [
            'referencia_externa.required_without' => 'Debe enviar referencia_externa o placa',
            'placa.required_without' => 'Debe enviar placa o referencia_externa',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $query = Pago::query();

            // Buscar por referencia externa (prioridad)
            if ($request->filled('referencia_externa')) {
                $query->where('referencia_pago', $request->referencia_externa);
            } else {
                // Buscar por placa + año fiscal
                $query->where('placa', strtoupper($request->placa));
                $anio = $request->anio_fiscal ?? date('Y');
                $query->where('anio_fiscal', $anio);
            }

            $pago = $query->first();

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ningún pago con los datos proporcionados',
                ], 404);
            }

            Log::info('API: Verificación de pago consultada', [
                'pago_id' => $pago->id,
                'entidad' => $request->entidad_nombre,
                'api_token_id' => $request->api_token_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pago encontrado',
                'data' => [
                    'pago_id' => $pago->id,
                    'comprobante' => 'PAG-' . str_pad($pago->id, 6, '0', STR_PAD_LEFT),
                    'placa' => $pago->placa,
                    'anio_fiscal' => $pago->anio_fiscal,
                    'monto_impuesto' => round((float) $pago->monto_impuesto, 2),
                    'monto_total' => round((float) $pago->monto_total, 2),
                    'estado' => $pago->estado,
                    'referencia_pago' => $pago->referencia_pago,
                    'fecha_pago' => $pago->fecha_pago?->format('Y-m-d H:i:s'),
                    'fecha_registro' => $pago->created_at->format('Y-m-d H:i:s'),
                    'entidad_recaudadora' => $pago->datos_adicionales['entidad_recaudadora'] ?? null,
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('API: Error al verificar pago', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el pago',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Reporte de conciliación para entidades bancarias
     * 
     * Devuelve los pagos registrados por la entidad autenticada
     * en un rango de fechas, con totales para cuadrar caja.
     * 
     * POST /api/v1/reporte-conciliacion
     */
    public function reporteConciliacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_desde' => 'required|date|date_format:Y-m-d',
            'fecha_hasta' => 'required|date|date_format:Y-m-d|after_or_equal:fecha_desde',
        ], [
            'fecha_desde.required' => 'La fecha de inicio es obligatoria',
            'fecha_desde.date_format' => 'Formato: YYYY-MM-DD',
            'fecha_hasta.required' => 'La fecha de fin es obligatoria',
            'fecha_hasta.date_format' => 'Formato: YYYY-MM-DD',
            'fecha_hasta.after_or_equal' => 'La fecha fin debe ser igual o posterior a la fecha inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $desde = $request->fecha_desde . ' 00:00:00';
            $hasta = $request->fecha_hasta . ' 23:59:59';
            $apiTokenId = $request->api_token_id;

            // Consultar pagos de ESTA entidad en el rango de fechas
            // Busca por api_token_id (nuevo) O por entidad_recaudadora en JSON (retrocompatibilidad)
            $pagos = Pago::where(function ($q) use ($apiTokenId, $request) {
                    $q->where('api_token_id', $apiTokenId)
                      ->orWhere('datos_adicionales->entidad_recaudadora', $request->entidad_nombre);
                })
                ->whereBetween('created_at', [$desde, $hasta])
                ->orderBy('created_at', 'asc')
                ->get();

            // Calcular totales
            $pagados = $pagos->where('estado', 'pagado');
            $pendientes = $pagos->where('estado', 'pendiente');
            $fallidos = $pagos->where('estado', 'fallido');

            // Detalle de cada pago
            $detalle = $pagos->map(function ($pago) {
                return [
                    'pago_id' => $pago->id,
                    'comprobante' => 'PAG-' . str_pad($pago->id, 6, '0', STR_PAD_LEFT),
                    'placa' => $pago->placa,
                    'anio_fiscal' => $pago->anio_fiscal,
                    'monto_total' => round((float) $pago->monto_total, 2),
                    'estado' => $pago->estado,
                    'referencia_pago' => $pago->referencia_pago,
                    'fecha_pago' => $pago->fecha_pago?->format('Y-m-d H:i:s'),
                    'fecha_registro' => $pago->created_at->format('Y-m-d H:i:s'),
                ];
            });

            Log::info('API: Reporte de conciliación generado', [
                'entidad' => $request->entidad_nombre,
                'api_token_id' => $apiTokenId,
                'desde' => $request->fecha_desde,
                'hasta' => $request->fecha_hasta,
                'total_registros' => $pagos->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reporte de conciliación generado',
                'data' => [
                    'entidad' => $request->entidad_nombre,
                    'periodo' => [
                        'desde' => $request->fecha_desde,
                        'hasta' => $request->fecha_hasta,
                    ],
                    'resumen' => [
                        'total_transacciones' => $pagos->count(),
                        'pagados' => [
                            'cantidad' => $pagados->count(),
                            'monto_total' => round($pagados->sum('monto_total'), 2),
                        ],
                        'pendientes' => [
                            'cantidad' => $pendientes->count(),
                            'monto_total' => round($pendientes->sum('monto_total'), 2),
                        ],
                        'fallidos' => [
                            'cantidad' => $fallidos->count(),
                            'monto_total' => round($fallidos->sum('monto_total'), 2),
                        ],
                    ],
                    'detalle_pagos' => $detalle,
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('API: Error al generar reporte de conciliación', [
                'error' => $e->getMessage(),
                'entidad' => $request->entidad_nombre ?? 'desconocida',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Simulador de cálculo de rodaje y mora (solo para pruebas)
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

        $detallesRubro = [];
        for ($anio = $anioInicio; $anio <= $anioActual; $anio++) {
            $totalFicticio += $valorAnual;

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

                if ($aniosAtraso > 5) {
                    $detallesRubro[] = [
                        'descripcion' => 'PRESCRIPCION',
                        'anio' => $anio,
                        'valor' => round($valorAnual * -0.10, 2),
                    ];
                }
            }
        }

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

    /**
     * Reporte administrativo de conciliación para el GAD
     *
     * Permite ver TODOS los pagos registrados o filtrar por entidad.
     * A diferencia de /reporte-conciliacion (que solo muestra los pagos
     * de la entidad autenticada), este muestra todo para comparar.
     *
     * POST /api/v1/admin/reporte-conciliacion
     */
    public function reporteAdminConciliacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_desde' => 'required|date|date_format:Y-m-d',
            'fecha_hasta' => 'required|date|date_format:Y-m-d|after_or_equal:fecha_desde',
            'entidad' => 'nullable|string|max:100',
            'estado' => 'nullable|in:pagado,pendiente,fallido,expirado',
            'placa' => 'nullable|string|max:10',
        ], [
            'fecha_desde.required' => 'La fecha de inicio es obligatoria',
            'fecha_desde.date_format' => 'Formato: YYYY-MM-DD',
            'fecha_hasta.required' => 'La fecha de fin es obligatoria',
            'fecha_hasta.date_format' => 'Formato: YYYY-MM-DD',
            'fecha_hasta.after_or_equal' => 'La fecha fin debe ser igual o posterior a la fecha inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $desde = $request->fecha_desde . ' 00:00:00';
            $hasta = $request->fecha_hasta . ' 23:59:59';

            $query = Pago::whereBetween('created_at', [$desde, $hasta]);

            // Filtro opcional por entidad
            if ($request->filled('entidad')) {
                $entidad = $request->entidad;
                $query->where('datos_adicionales->entidad_recaudadora', 'like', "%{$entidad}%");
            }

            // Filtro opcional por estado
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            // Filtro opcional por placa
            if ($request->filled('placa')) {
                $query->where('placa', strtoupper($request->placa));
            }

            $pagos = $query->orderBy('created_at', 'asc')->get();

            // Totales generales
            $pagados = $pagos->where('estado', 'pagado');
            $pendientes = $pagos->where('estado', 'pendiente');
            $fallidos = $pagos->where('estado', 'fallido');

            // Agrupar por entidad para comparar
            $porEntidad = $pagos->groupBy(function ($pago) {
                return $pago->datos_adicionales['entidad_recaudadora'] ?? 'Sin entidad';
            })->map(function ($pagosPorEntidad, $nombreEntidad) {
                $pagadosEntidad = $pagosPorEntidad->where('estado', 'pagado');
                return [
                    'entidad' => $nombreEntidad,
                    'total_transacciones' => $pagosPorEntidad->count(),
                    'pagados' => $pagadosEntidad->count(),
                    'monto_total_pagado' => round($pagadosEntidad->sum('monto_total'), 2),
                    'pendientes' => $pagosPorEntidad->where('estado', 'pendiente')->count(),
                    'fallidos' => $pagosPorEntidad->where('estado', 'fallido')->count(),
                ];
            })->values();

            // Detalle de cada pago
            $detalle = $pagos->map(function ($pago) {
                return [
                    'pago_id' => $pago->id,
                    'comprobante' => 'PAG-' . str_pad($pago->id, 6, '0', STR_PAD_LEFT),
                    'placa' => $pago->placa,
                    'anio_fiscal' => $pago->anio_fiscal,
                    'monto_impuesto' => round((float) $pago->monto_impuesto, 2),
                    'monto_total' => round((float) $pago->monto_total, 2),
                    'estado' => $pago->estado,
                    'referencia_pago' => $pago->referencia_pago,
                    'fecha_pago' => $pago->fecha_pago?->format('Y-m-d H:i:s'),
                    'fecha_registro' => $pago->created_at->format('Y-m-d H:i:s'),
                    'entidad_recaudadora' => $pago->datos_adicionales['entidad_recaudadora'] ?? null,
                    'metodo_pago' => $pago->datos_adicionales['metodo_pago'] ?? null,
                ];
            });

            Log::info('API: Reporte admin de conciliación generado', [
                'solicitado_por' => $request->entidad_nombre,
                'desde' => $request->fecha_desde,
                'hasta' => $request->fecha_hasta,
                'filtro_entidad' => $request->entidad,
                'total_registros' => $pagos->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reporte administrativo de conciliación generado',
                'data' => [
                    'periodo' => [
                        'desde' => $request->fecha_desde,
                        'hasta' => $request->fecha_hasta,
                    ],
                    'filtros_aplicados' => [
                        'entidad' => $request->entidad ?? 'Todas',
                        'estado' => $request->estado ?? 'Todos',
                        'placa' => $request->placa ?? null,
                    ],
                    'resumen_general' => [
                        'total_transacciones' => $pagos->count(),
                        'pagados' => [
                            'cantidad' => $pagados->count(),
                            'monto_total' => round($pagados->sum('monto_total'), 2),
                        ],
                        'pendientes' => [
                            'cantidad' => $pendientes->count(),
                            'monto_total' => round($pendientes->sum('monto_total'), 2),
                        ],
                        'fallidos' => [
                            'cantidad' => $fallidos->count(),
                            'monto_total' => round($fallidos->sum('monto_total'), 2),
                        ],
                    ],
                    'resumen_por_entidad' => $porEntidad,
                    'detalle_pagos' => $detalle,
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('API: Error en reporte admin de conciliación', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }
}
