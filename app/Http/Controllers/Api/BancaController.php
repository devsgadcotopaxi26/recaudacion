<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransaccionPago;
use App\Models\PagoDetalle;
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
            // Consultar deuda del SRI + cruce con pagos locales. Lógica
            // compartida con el panel admin (/admin/consulta-api) vía
            // DeudaVehicularService — una sola implementación de la regla
            // "qué años están realmente pendientes".
            $deudaService = new \App\Services\DeudaVehicularService(new \App\Services\SriVehiculoService());
            $resultado = $deudaService->consultar($placa);

            // Registrar la consulta en la base de datos
            // ── Guardia: no grabar si el resultado es incoherente (deuda con $0) ──
            $metodoSri    = $resultado['metodo_sri'];
            $totalAPagar  = $resultado['totales_sri']['total_a_pagar'] ?? 0;
            $esIncoherente = ($metodoSri === 'deuda' && $totalAPagar <= 0);

            // Generar código único de consulta: CON-YYYYMMDD-XXXXX
            $codigoConsulta = 'CON-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $consultaRegistrada = null;

            if (!$esIncoherente) {
                try {
                    $consultaRegistrada = \App\Models\ConsultaBancaria::create([
                        'codigo_consulta' => $codigoConsulta,
                        'api_token_id'   => $request->api_token_id,
                        'placa'          => $placa,
                        'anio_fiscal'    => $anioFiscal,
                        'metodo_sri'     => $metodoSri,
                        'valor_matricula'=> $resultado['valor_matricula'] ?? 0,
                        'total_rodaje'   => $resultado['totales_sri']['total_rodaje'] ?? 0,
                        'total_mora'     => $resultado['totales_sri']['total_mora'] ?? 0,
                        'total_a_pagar'  => $totalAPagar,
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
                'total_a_pagar' => $resultado['totales_sri']['total_a_pagar'],
                'metodo' => $metodoSri,
            ]);

            // Forma de 'data' definida en un único lugar
            // (DeudaVehicularService::formatearRespuestaPublica) — también la
            // usa ConsultaApiController para previsualizar en
            // /admin/consulta-api, así ambas no pueden divergir sin que se note.
            return response()->json([
                'success' => true,
                'data' => $deudaService->formatearRespuestaPublica(
                    $resultado,
                    $consultaRegistrada?->codigo_consulta ?? $codigoConsulta
                ),
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
            // Ya NO se usa como fuente de verdad de la entidad (ver más abajo:
            // se toma siempre de $request->entidad_nombre, inyectado por
            // ValidateApiToken desde el token autenticado). Se acepta si el
            // banco lo sigue mandando, para no romper integraciones
            // existentes, pero se ignora su valor.
            'entidad_recaudadora' => 'nullable|string|max:100',
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
                $detalleExistente = PagoDetalle::where('placa', $placa)
                    ->where('anio_fiscal', $anioFiscal)
                    ->where('estado', 'pagado')
                    ->with('transaccionPago')
                    ->first();

                if ($detalleExistente) {
                    $transaccionExistente = $detalleExistente->transaccionPago;
                    return response()->json([
                        'success' => false,
                        'message' => "El vehículo ya tiene el impuesto pagado para el año {$anioFiscal}",
                        'pago_existente' => [
                            'codigo_consulta' => $transaccionExistente->codigo_consulta,
                            // true = pago anterior a la exigencia de codigo_consulta (sin código legítimamente,
                            // no es un dato corrupto ni faltante).
                            'registro_historico' => is_null($transaccionExistente->codigo_consulta),
                            'fecha_pago' => $transaccionExistente->fecha_pago,
                            'referencia' => $transaccionExistente->referencia_externa,
                            'monto' => $detalleExistente->monto_total
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
            $pagosExistentes = PagoDetalle::where('placa', $placa)
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

            // Crear la transacción (cabecera) + un detalle por cada año
            // pendiente que se está pagando, todo dentro de UNA sola
            // DB::transaction() — si cualquier fila falla, se revierte
            // completo (cabecera y detalles ya creados incluidos). Esto
            // resuelve de raíz el bug de atomicidad detectado antes (un
            // pago de varios años ya no puede quedar a medias committeado).
            [$transaccion, $pagosCreados] = DB::transaction(function () use (
                $placa,
                $aniosAPagar,
                $monto,
                $request,
                $consulta,
                $datos
            ) {
                $transaccion = TransaccionPago::create([
                    'placa' => $placa,
                    'canal' => 'banco',
                    'referencia_externa' => $request->referencia_externa,
                    'codigo_consulta' => $consulta->codigo_consulta,
                    'consulta_bancaria_id' => $consulta->id,
                    // La entidad se identifica SIEMPRE vía esta FK (token
                    // autenticado, inyectado por ValidateApiToken, verificado
                    // contra api_tokens) — no se guarda texto libre. El campo
                    // 'entidad_recaudadora' que el banco pueda seguir
                    // mandando en el body se acepta pero se ignora (ver regla
                    // del Validator más arriba); el nombre real siempre sale
                    // de TransaccionPago::nombreEntidad().
                    'api_token_id' => $request->api_token_id,
                    'monto_total' => round($monto, 2),
                    'estado' => 'pagado',
                    // Ya no la reporta el banco (ver auditoría: dejó de ser
                    // input aceptado en el request). Se fija al momento del
                    // registro, mismo criterio que ya usaba
                    // TransaccionPago::marcarComoPagado() para la pasarela
                    // ciudadana. NUNCA usar este campo para calcular mora,
                    // la ventana de 24h de codigo_consulta, o filtrar
                    // reportes de conciliación — esos tres SIEMPRE usan
                    // `created_at`.
                    'fecha_pago' => now(),
                    'datos_adicionales' => [
                        'metodo_pago' => 'API_Bancaria',
                        'vehiculo' => $datos['vehiculo'],
                    ],
                ]);

                $pagosCreados = [];
                $sumaDetalle = 0;
                foreach ($aniosAPagar as $anioPago) {
                    $detalleCreado = $transaccion->detalles()->create([
                        'placa' => $placa,
                        'estado' => 'pagado',
                        'anio_fiscal' => $anioPago['anio'],
                        'monto_impuesto' => $anioPago['rodaje'] ?? 0,
                        'monto_mora' => $anioPago['mora'] ?? 0,
                        'monto_total' => $anioPago['valor'],
                    ]);

                    // Suma del valor REALMENTE persistido (no del array
                    // fuente antes de guardar) — si algo mutara el monto
                    // durante el create() (evento de modelo, etc.), la
                    // guarda de abajo debe reflejar lo que de verdad quedó
                    // en la fila, no lo que se intentó escribir.
                    $sumaDetalle += (float) $detalleCreado->monto_total;

                    $pagosCreados[] = [
                        'anio_fiscal' => $anioPago['anio'],
                        'monto' => round($anioPago['valor'], 2),
                    ];
                }

                // Integridad cabecera/detalle: no existe trigger de BD ni
                // otra validación que garantice esto (confirmado en
                // auditoría) — si algún día un cálculo de $aniosAPagar
                // queda desincronizado con $monto_total, esta es la última
                // línea de defensa antes de comprometer la transacción.
                // Lanzar aquí revierte TODO (cabecera + detalles ya
                // creados) porque estamos dentro del DB::transaction().
                //
                // Tolerancia de $1.00, NO igualdad estricta: monto_total
                // de la cabecera guarda lo que reportó el banco ($monto,
                // ya validado arriba contra $montoEsperado con esa misma
                // tolerancia), mientras que $sumaDetalle sale del cálculo
                // del SRI — pueden diferir por diseño hasta $1.00 en
                // operación normal. Esta guarda es para atrapar un
                // descuadre GRUESO (ej. un detalle creado con monto
                // equivocado por un bug futuro), no la tolerancia ya
                // aceptada intencionalmente más arriba.
                if (abs((float) $transaccion->monto_total - $sumaDetalle) > 1.00) {
                    throw new \RuntimeException(sprintf(
                        'Descuadre cabecera/detalle: monto_total=%s, suma_detalle=%s (transaccion sin persistir, placa=%s)',
                        round((float) $transaccion->monto_total, 2),
                        round($sumaDetalle, 2),
                        $placa
                    ));
                }

                // Marcar la consulta como pagada
                $consulta->update(['estado' => 'pagado']);

                return [$transaccion, $pagosCreados];
            });

            // Borrar el caché de "deuda pendiente" (incorpora PagoDetalle
            // local, ver SriVehiculoService::consultarVehiculoCompleto())
            // para que la próxima consulta muestre el pago de inmediato.
            // NO se borra sri:detalle:{placa} (dato crudo del SRI, no
            // cambia por este pago) — evita una llamada real al SRI
            // innecesaria en la siguiente consulta.
            \Illuminate\Support\Facades\Cache::forget("sri_full_v3_{$placa}");

            Log::info('API: Pago registrado exitosamente', [
                'placa' => $placa,
                'transaccion_pago_id' => $transaccion->id,
                'monto_total' => $monto,
                'anios_pagados' => $aniosAPagar->pluck('anio')->toArray(),
                'entidad' => $request->entidad_nombre,
                'referencia' => $request->referencia_externa,
                'codigo_consulta' => $consulta->codigo_consulta,
                'api_token_id' => $request->api_token_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'data' => [
                    'codigo_consulta' => $consulta->codigo_consulta,
                    'placa' => $placa,
                    // 'comprobante' (PAG-XXXXXX) ya NO se expone al banco —
                    // es información interna/contable; el banco ya tiene
                    // codigo_transaccion para verificación pública y
                    // referencia_externa para su propia reconciliación.
                    // El campo y comprobante() del modelo siguen existiendo
                    // igual para uso interno (certificados, admin).
                    //
                    // codigo_transaccion: clave para construir la URL de
                    // verificación pública (GET /verificar/{codigo_transaccion})
                    // sin exponer un identificador secuencial ni depender de
                    // referencia_externa — ver auditoría de seguridad.
                    'codigo_transaccion' => $transaccion->codigo_transaccion,
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
            'codigo_transaccion' => 'nullable|string|max:32',
            'referencia_externa' => 'nullable|string|max:100',
        ]);

        // Debe enviar al menos uno
        if (!$request->filled('codigo_transaccion') && !$request->filled('referencia_externa')) {
            return response()->json([
                'success' => false,
                'message' => 'Debe enviar codigo_transaccion o referencia_externa',
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
            $transaccion = null;

            // codigo_transaccion (prioridad 1, criterio principal): el
            // código corto TRX-XXXXXX. referencia_externa (prioridad 2,
            // recuperación): para cuando el banco tuvo un timeout y no
            // llegó a recibir codigo_transaccion — no sabe si el pago se
            // registró o no (ver idempotencia en el manual). Ya NO se
            // acepta codigo_consulta ni placa+anio_fiscal como criterio de
            // este endpoint — codigo_consulta identifica una CONSULTA de
            // deuda, no un pago, y placa+año podía devolver un año dentro
            // de una transacción distinta a la que el banco realmente
            // quería verificar.
            //
            // Ambas ramas se acotan a api_token_id === $request->api_token_id
            // (inyectado por ValidateApiToken desde el token autenticado):
            // sin esto, un banco autenticado con SU PROPIO token podía
            // encontrar transacciones de CUALQUIER otra entidad si conocía
            // o adivinaba su codigo_transaccion/referencia_externa (ver
            // auditoría de seguridad) — fuga entre bancos/cooperativas
            // competidores, no acceso público.
            if ($request->filled('codigo_transaccion')) {
                $transaccion = TransaccionPago::where('codigo_transaccion', $request->codigo_transaccion)
                    ->where('api_token_id', $request->api_token_id)
                    ->first();
            } elseif ($request->filled('referencia_externa')) {
                $transaccion = TransaccionPago::where('referencia_externa', $request->referencia_externa)
                    ->where('api_token_id', $request->api_token_id)
                    ->first();
            }

            if (!$transaccion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ningún pago con los datos proporcionados',
                ], 404);
            }

            $transaccion->loadMissing(['detalles', 'apiToken']);

            Log::info('API: Verificación de pago consultada', [
                'transaccion_pago_id' => $transaccion->id,
                'entidad' => $request->entidad_nombre,
                'api_token_id' => $request->api_token_id,
            ]);

            $detalles = $transaccion->detalles->map(function ($d) {
                return [
                    'anio_fiscal' => $d->anio_fiscal,
                    'monto_impuesto' => round((float) $d->monto_impuesto, 2),
                    'monto_mora' => round((float) $d->monto_mora, 2),
                    'monto_total' => round((float) $d->monto_total, 2),
                    'estado' => $d->estado,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Pago encontrado',
                'data' => [
                    // transaccion_id (el id crudo) ya no se expone al banco —
                    // mismo riesgo de enumeración que 'comprobante', sin el
                    // disfraz del prefijo "PAG-". codigo_transaccion sigue
                    // siendo la clave pública de verificación.
                    'codigo_consulta' => $transaccion->codigo_consulta,
                    // true = pago anterior a la exigencia de codigo_consulta (sin código legítimamente).
                    'registro_historico' => is_null($transaccion->codigo_consulta),
                    'placa' => $transaccion->placa,
                    'monto_total' => round((float) $transaccion->monto_total, 2),
                    'anios_cubiertos' => $detalles->count(),
                    'detalles' => $detalles,
                    'estado' => $transaccion->estado,
                    'referencia_pago' => $transaccion->referencia_externa,
                    'fecha_pago' => $transaccion->fecha_pago?->format('Y-m-d H:i:s'),
                    'fecha_registro' => $transaccion->created_at->format('Y-m-d H:i:s'),
                    'entidad_recaudadora' => $transaccion->nombreEntidad(),
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

            // Consultar los detalles (una fila por año-detalle, igual que
            // antes) de ESTA entidad en el rango de fechas. api_token_id es
            // la única fuente de verdad de "de quién es esta transacción"
            // (cada entidad tiene siempre un único token por ambiente).
            $pagos = PagoDetalle::whereHas('transaccionPago', function ($q) use ($apiTokenId) {
                    $q->where('api_token_id', $apiTokenId);
                })
                ->with('transaccionPago')
                ->whereBetween('created_at', [$desde, $hasta])
                ->orderBy('created_at', 'asc')
                ->get();

            // Calcular totales
            $pagados = $pagos->where('estado', 'pagado');
            $pendientes = $pagos->where('estado', 'pendiente');
            $fallidos = $pagos->where('estado', 'fallido');

            // Detalle de cada pago (referencia sale de la transacción —
            // compartida entre todos los años de un mismo pago). 'comprobante'
            // ya no se expone al banco (info interna/contable). 'pago_id'
            // mantiene su NOMBRE (contrato público documentado, no se
            // renombra) pero ya no es el id crudo autoincremental — mismo
            // riesgo de enumeración que comprobante/transaccion_id, sin
            // ningún disfraz. Ahora lleva codigo_transaccion (formato
            // TRX-XXXXXX, no adivinable), sigue identificando la
            // transacción sin ambigüedad para quien consuma el reporte.
            $detalle = $pagos->map(function ($d) {
                return [
                    'pago_id' => $d->transaccionPago->codigo_transaccion,
                    'placa' => $d->placa,
                    'anio_fiscal' => $d->anio_fiscal,
                    'monto_total' => round((float) $d->monto_total, 2),
                    'estado' => $d->estado,
                    'referencia_pago' => $d->transaccionPago->referencia_externa,
                    'fecha_pago' => $d->transaccionPago->fecha_pago?->format('Y-m-d H:i:s'),
                    'fecha_registro' => $d->created_at->format('Y-m-d H:i:s'),
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
     * Reporte administrativo de conciliación
     *
     * CORREGIDO (auditoría de seguridad): este endpoint vive bajo el mismo
     * middleware api.token que el resto de la API bancaria — cualquier
     * entidad autenticada con SU PROPIO token puede llamarlo, no solo el
     * GAD. Antes de este fix devolvía los pagos de TODAS las entidades sin
     * filtrar, permitiendo que un banco viera la conciliación de sus
     * competidores. Ahora se acota SIEMPRE por api_token_id, igual que
     * verificarPago() y reporteConciliacion() — en la práctica, un banco ve
     * exactamente lo mismo aquí que en /reporte-conciliacion (misma
     * entidad), solo que con el resumen_por_entidad/filtros adicionales
     * que ya tenía este endpoint.
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
            // Lógica de filtrado/resumen centralizada en ConciliacionReporteService
            // (compartida con el panel admin en /admin/reporte-conciliacion) para
            // no mantener dos implementaciones de la misma regla de negocio.
            $service = new \App\Services\ConciliacionReporteService();

            $query = $service->construirQuery([
                'fecha_desde' => $request->fecha_desde,
                'fecha_hasta' => $request->fecha_hasta,
                'entidad' => $request->entidad,
                'estado' => $request->estado,
                'placa' => $request->placa,
                'anio_fiscal' => $request->anio_fiscal,
                'codigo_consulta' => $request->codigo_consulta,
                // Scope obligatorio — ver docblock del método. Sin esto,
                // cualquier banco veía la conciliación de todas las
                // entidades.
                'api_token_id' => $request->api_token_id,
            ]);

            $pagos = $query->orderBy('created_at', 'asc')->get();

            // Totales generales
            $pagados = $pagos->where('estado', 'pagado');
            $pendientes = $pagos->where('estado', 'pendiente');
            $fallidos = $pagos->where('estado', 'fallido');

            // Agrupar por entidad para comparar
            $porEntidad = $service->resumenPorEntidad($pagos);

            // Detalle de cada pago — variante BANCARIA (sin id crudo ni
            // comprobante, ver ConciliacionReporteService).
            $detalle = $pagos->map(fn($pago) => $service->formatearDetalleBancario($pago));

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