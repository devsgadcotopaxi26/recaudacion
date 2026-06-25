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

            // Obtener TODOS los pagos existentes de esta placa
            $pagosExistentes = Pago::where('placa', $placa)
                ->where('estado', 'pagado')
                ->get()
                ->keyBy('anio_fiscal');

            // Marcar cada año del desglose como pagado o pendiente
            $desgloseConEstado = collect($datos['desglose_anual'])->map(function ($anio) use ($pagosExistentes) {
                $pago = $pagosExistentes->get($anio['anio']);
                $anio['estado'] = $pago ? 'pagado' : 'pendiente';
                if ($pago) {
                    $anio['pago'] = [
                        'pago_id' => $pago->id,
                        'comprobante' => 'PAG-' . str_pad($pago->id, 6, '0', STR_PAD_LEFT),
                        'codigo_consulta' => $pago->datos_adicionales['codigo_consulta'] ?? null,
                        'referencia' => $pago->referencia_pago,
                        'fecha_pago' => $pago->fecha_pago?->format('Y-m-d H:i:s'),
                        'entidad' => $pago->datos_adicionales['entidad_recaudadora'] ?? null,
                    ];
                }
                return $anio;
            })->toArray();

            // Calcular totales solo de años pendientes
            $aniosPendientes = collect($desgloseConEstado)->where('estado', 'pendiente');
            $totalPendiente = round($aniosPendientes->sum('valor'), 2);
            $totalRodajePendiente = round($aniosPendientes->sum('rodaje'), 2);
            $totalMoraPendiente = round($aniosPendientes->sum('mora'), 2);

            // Si todo está pagado, informar
            $todosPagados = $aniosPendientes->isEmpty();

            $vehiculo = $datos['vehiculo'];

            // Registrar la consulta en la base de datos
            // ── Guardia: no grabar si el resultado es incoherente (deuda con $0) ──
            $metodoSri    = $datos['metodo_sri'] ?? 'desconocido';
            $totalAPagar  = $datos['totales']['total_a_pagar'] ?? 0;
            $esIncoherente = ($metodoSri === 'deuda' && $totalAPagar <= 0);

            // Generar código único de consulta: CON-YYYYMMDD-XXXXX
            $codigoConsulta = 'CON-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $consultaRegistrada = null;

            if (!$esIncoherente) {
                try {
                    $consultaRegistrada = \App\Models\ConsultaBancaria::create([
                        'codigo_consulta' => $codigoConsulta,
                        'api_token_id'   => $request->api_token_id,
                        'entidad_nombre' => $request->entidad_nombre,
                        'placa'          => $placa,
                        'anio_fiscal'    => $anioFiscal,
                        'metodo_sri'     => $metodoSri,
                        'valor_matricula'=> $datos['valor_matricula'] ?? 0,
                        'total_rodaje'   => $datos['totales']['total_rodaje'] ?? 0,
                        'total_mora'     => $datos['totales']['total_mora'] ?? 0,
                        'total_a_pagar'  => $totalAPagar,
                        'monto_a_pagar'  => $totalAPagar,
                        'estado'         => 'pendiente',
                        'expira_en'      => now()->addHours(24),
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
                    'codigo_consulta' => $consultaRegistrada?->codigo_consulta ?? $codigoConsulta,
                    'placa' => $vehiculo['placa'],
                    'vehiculo' => [
                        'marca' => $vehiculo['marca'],
                        'modelo' => $vehiculo['modelo'],
                        'anio' => $vehiculo['anio'],
                        'tipo' => $vehiculo['clase'] ?? 'automovil',
                        'descripcion' => $vehiculo['descripcion_completa'] ?? '',
                    ],
                    'valor_matricula' => $datos['valor_matricula'],
                    'todos_pagados' => $todosPagados,
                    'desglose_anual' => $desgloseConEstado,
                    'totales_sri' => $datos['totales'],
                    'totales_pendientes' => [
                        'total_rodaje' => $totalRodajePendiente,
                        'total_mora' => $totalMoraPendiente,
                        'total_a_pagar' => $totalPendiente,
                    ],
                    'nota' => $todosPagados
                        ? 'Todos los años están pagados. No hay deuda pendiente.'
                        : 'Use el codigo_consulta al registrar el pago. Válido por 24 horas.',
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

            $status = (int) $e->getCode();
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
            'anio_fiscal' => 'nullable|integer|min:2020|max:2030',
            'monto' => 'required|numeric|min:0.01',
            'codigo_consulta' => 'required|string|max:30',
            'referencia_externa' => 'required|string|max:100',
            'fecha_pago' => 'required|date',
            'entidad_recaudadora' => 'required|string|max:100',
        ], [
            'placa.required' => 'La placa es obligatoria',
            'placa.max' => 'La placa no puede exceder los 10 caracteres',
            'placa.string' => 'El formato de la placa es inválido',
            'codigo_consulta.required' => 'El código de consulta es obligatorio. Primero consulte la deuda.',
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
            // Verificar si ya existe un pago para el año específico
            if ($anioFiscal) {
                $pagoExistente = Pago::where('placa', $placa)
                    ->where('anio_fiscal', $anioFiscal)
                    ->where('estado', 'pagado')
                    ->first();

                if ($pagoExistente) {
                    return response()->json([
                        'success' => false,
                        'message' => "El vehículo ya tiene el impuesto pagado para el año {$anioFiscal}",
                        'pago_existente' => [
                            'id' => $pagoExistente->id,
                            'comprobante' => 'PAG-' . str_pad($pagoExistente->id, 6, '0', STR_PAD_LEFT),
                            'codigo_consulta' => $pagoExistente->datos_adicionales['codigo_consulta'] ?? null,
                            'fecha_pago' => $pagoExistente->fecha_pago,
                            'referencia' => $pagoExistente->referencia_pago,
                            'monto' => $pagoExistente->monto_total
                        ]
                    ], 400);
                }
            }

            // Validar código de consulta
            $consulta = \App\Models\ConsultaBancaria::where('codigo_consulta', $request->codigo_consulta)
                ->first();

            if (!$consulta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Código de consulta no encontrado. Primero debe consultar la deuda.',
                ], 400);
            }

            if ($consulta->estado === 'pagado') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este código de consulta ya fue utilizado para registrar un pago.',
                ], 400);
            }

            if ($consulta->expira_en && now()->greaterThan($consulta->expira_en)) {
                $consulta->update(['estado' => 'expirado']);
                return response()->json([
                    'success' => false,
                    'message' => 'El código de consulta expiró. Realice una nueva consulta de deuda.',
                ], 400);
            }

            if (strtoupper($consulta->placa) !== $placa) {
                return response()->json([
                    'success' => false,
                    'message' => 'La placa no coincide con la consulta original.',
                ], 400);
            }

            // Consultar SRI para validar monto
            $sriService = new \App\Services\SriVehiculoService();
            $datos = $sriService->consultarVehiculoCompleto($placa);

            // Obtener pagos existentes de esta placa
            $pagosExistentes = Pago::where('placa', $placa)
                ->where('estado', 'pagado')
                ->pluck('anio_fiscal')
                ->toArray();

            // Filtrar solo años pendientes del desglose
            $aniosPendientes = collect($datos['desglose_anual'])->filter(function ($anio) use ($pagosExistentes) {
                return !in_array($anio['anio'], $pagosExistentes);
            })->values();

            if ($aniosPendientes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay años pendientes de pago para esta placa.',
                ], 400);
            }

            $totalPendiente = round($aniosPendientes->sum('valor'), 2);

            // Determinar si paga un año específico o todos los pendientes
            $aniosAPagar = collect();
            if ($aniosPendientes->count() === 1) {
                // Solo un año pendiente, el monto debe coincidir
                $aniosAPagar = $aniosPendientes;
                $montoEsperado = $totalPendiente;
            } elseif ($anioFiscal && $aniosPendientes->where('anio', $anioFiscal)->isNotEmpty()) {
                // Paga un año específico
                $aniosAPagar = $aniosPendientes->where('anio', $anioFiscal)->values();
                $montoEsperado = round($aniosAPagar->sum('valor'), 2);
            } else {
                // Paga todos los pendientes
                $aniosAPagar = $aniosPendientes;
                $montoEsperado = $totalPendiente;
            }

            // Verificar que el monto sea correcto (tolerancia de $1.00)
            $diferencia = abs($monto - $montoEsperado);

            if ($diferencia > 1.00) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto enviado no coincide con el total a pagar',
                    'monto_enviado' => round($monto, 2),
                    'monto_esperado' => round($montoEsperado, 2),
                    'diferencia' => round($diferencia, 2),
                    'detalle_pendiente' => $aniosAPagar->map(function ($a) {
                        return ['anio' => $a['anio'], 'valor' => $a['valor']];
                    }),
                ], 400);
            }

            // Crear un pago por cada año pendiente que se está pagando
            $pagosCreados = [];
            foreach ($aniosAPagar as $anioPago) {
                $pago = Pago::create([
                    'placa' => $placa,
                    'anio_fiscal' => $anioPago['anio'],
                    'monto_impuesto' => $anioPago['valor'],
                    'monto_total' => $anioPago['valor'],
                    'estado' => 'pagado',
                    'referencia_pago' => $request->referencia_externa,
                    'fecha_pago' => $request->fecha_pago,
                    'api_token_id' => $request->api_token_id,
                    'consulta_bancaria_id' => $consulta->id,
                    'datos_adicionales' => [
                        'metodo_pago' => 'API_Bancaria',
                        'entidad_recaudadora' => $request->entidad_recaudadora,
                        'codigo_consulta' => $request->codigo_consulta,
                        'vehiculo' => $datos['vehiculo']
                    ],
                ]);

                $pagosCreados[] = [
                    'pago_id' => $pago->id,
                    'comprobante' => 'PAG-' . str_pad($pago->id, 6, '0', STR_PAD_LEFT),
                    'anio_fiscal' => $anioPago['anio'],
                    'monto' => round($anioPago['valor'], 2),
                ];
            }

            // Marcar la consulta como pagada
            $consulta->update(['estado' => 'pagado']);

            // Borrar cache del SRI para que la próxima consulta muestre el estado real
            \Illuminate\Support\Facades\Cache::forget("sri_full_v3_{$placa}");
            \Illuminate\Support\Facades\Cache::forget("sri:detalle:{$placa}");

            Log::info('API: Pago registrado exitosamente', [
                'placa' => $placa,
                'monto_total' => $monto,
                'anios_pagados' => $aniosAPagar->pluck('anio')->toArray(),
                'entidad' => $request->entidad_recaudadora,
                'referencia' => $request->referencia_externa,
                'codigo_consulta' => $request->codigo_consulta,
                'api_token_id' => $request->api_token_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'data' => [
                    'codigo_consulta' => $request->codigo_consulta,
                    'placa' => $placa,
                    'monto_total_pagado' => round($monto, 2),
                    'anios_pagados' => count($pagosCreados),
                    'fecha_registro' => now()->format('Y-m-d H:i:s'),
                    'pagos' => $pagosCreados,
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

            $status = (int) $e->getCode();
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
            'codigo_consulta' => 'nullable|string|max:30',
            'referencia_externa' => 'nullable|string|max:100',
            'placa' => 'nullable|string|max:10',
            'anio_fiscal' => 'nullable|integer|min:2020|max:2030',
        ]);

        // Debe enviar al menos uno
        if (!$request->filled('codigo_consulta') && !$request->filled('referencia_externa') && !$request->filled('placa')) {
            return response()->json([
                'success' => false,
                'message' => 'Debe enviar al menos uno: codigo_consulta, referencia_externa, o placa',
            ], 400);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $query = Pago::query();

            // Buscar por codigo_consulta (prioridad 1)
            if ($request->filled('codigo_consulta')) {
                $query->where('datos_adicionales->codigo_consulta', $request->codigo_consulta);
            }
            // Buscar por referencia externa (prioridad 2)
            elseif ($request->filled('referencia_externa')) {
                $query->where('referencia_pago', $request->referencia_externa);
            }
            // Buscar por placa + año fiscal (prioridad 3)
            else {
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
                    'codigo_consulta' => $pago->datos_adicionales['codigo_consulta'] ?? null,
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
            'anio_fiscal' => 'nullable|integer|min:2020|max:2030',
            'codigo_consulta' => 'nullable|string|max:30',
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

            // Filtro opcional por año fiscal
            if ($request->filled('anio_fiscal')) {
                $query->where('anio_fiscal', $request->anio_fiscal);
            }

            // Filtro opcional por código de consulta
            if ($request->filled('codigo_consulta')) {
                $query->where('datos_adicionales->codigo_consulta', $request->codigo_consulta);
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
                    'codigo_consulta' => $pago->datos_adicionales['codigo_consulta'] ?? null,
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
                        'anio_fiscal' => $request->anio_fiscal ?? 'Todos',
                        'codigo_consulta' => $request->codigo_consulta ?? null,
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