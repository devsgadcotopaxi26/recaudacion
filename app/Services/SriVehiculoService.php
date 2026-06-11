<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\SriRequest;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException as HttpRequestException;

class SriVehiculoService
{
    private string $baseUrl;
    private int $timeout;
    private bool $cacheEnabled;
    private int $cacheTtl;
    private int $retryTimes;   // Intentos extra en errores 5xx/network
    private int $retryDelayMs; // Pausa entre reintentos (milisegundos)

    public function __construct()
    {
        $this->baseUrl = 'https://srienlinea.sri.gob.ec/movil-servicios/api/v1.0';
        $this->timeout = config('sri.timeout', 10);
        $this->cacheEnabled = config('sri.cache.enabled', true);
        $this->cacheTtl = config('sri.cache.ttl', 3600);
        $this->retryTimes = config('sri.retry.times', 2);    // 2 reintentos
        $this->retryDelayMs = config('sri.retry.delay_ms', 1500); // 1.5 s entre intentos
    }

    /**
     * Obtener detalle completo del vehículo desde el SRI.
     *
     * Incluye lógica de reintentos: si el SRI responde con 5xx o hay error
     * de red/timeout, reintenta hasta $retryTimes veces antes de lanzar
     * la excepción. Los 404 autenticos NO se reintentan.
     *
     * @throws Exception código 404 si la placa no existe.
     * @throws Exception código 503 si el SRI sigue fallando tras todos los reintentos.
     */
    public function obtenerDetalleCompleto(string $placa): array
    {
        $cacheKey = "sri:detalle:{$placa}";

        if ($this->cacheEnabled && Cache::has($cacheKey)) {
            Log::info('SRI: Detalle obtenido del caché', ['placa' => $placa]);
            return Cache::get($cacheKey);
        }

        $ultimoError = null;
        $intentosTotales = 1 + $this->retryTimes; // 1 intento inicial + reintentos

        for ($intento = 1; $intento <= $intentosTotales; $intento++) {
            try {
                $wasCached = false;

                return $this->trackRequest('detalle', $placa, $wasCached, function () use ($placa, $cacheKey) {
                    // 1. Obtener datos base del vehículo
                    $urlBase = 'https://srienlinea.sri.gob.ec/sri-matriculacion-vehicular-recaudacion-servicio-internet/rest/BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn';
                    Log::info('SRI: Obteniendo datos base del vehículo', ['placa' => $placa]);
                    $responseBase = Http::timeout($this->timeout)->get($urlBase, ['numeroPlacaCampvCpn' => $placa]);

                    if ($responseBase->status() === 404) {
                        throw new Exception('La placa no existe o no se encuentra registrada en el SRI', 404);
                    }

                    if (!$responseBase->successful()) {
                        Log::error('SRI: Error al obtener detalle base', [
                            'placa' => $placa,
                            'status' => $responseBase->status(),
                            'body' => $responseBase->body()
                        ]);
                        throw new Exception(
                            'Error al consultar datos base del vehículo en el SRI',
                            $responseBase->status() ?: 503
                        );
                    }

                    $dataVehiculo = $responseBase->json();

                    Log::channel('sri')->info('SRI: Response BaseVehiculo JSON', [
                        'placa' => $placa,
                        'body' => $dataVehiculo
                    ]);

                    if (empty($dataVehiculo) || empty($dataVehiculo['codigoVehiculo'])) {
                        throw new Exception('No se encontró información del vehículo o su código', 404);
                    }

                    $codigoVehiculo = $dataVehiculo['codigoVehiculo'];

                    // 2. Obtener rubros a pagar
                    $urlRubros = 'https://srienlinea.sri.gob.ec/sri-matriculacion-vehicular-recaudacion-servicio-internet/rest/ConsultaRubros/obtenerPorCodigoVehiculo';
                    Log::info('SRI: Obteniendo rubros del vehículo', ['codigoVehiculo' => $codigoVehiculo]);
                    $responseRubros = Http::timeout($this->timeout)->get($urlRubros, ['codigoVehiculo' => $codigoVehiculo]);

                    if (!$responseRubros->successful()) {
                        Log::error('SRI: Error al obtener rubros', [
                            'codigoVehiculo' => $codigoVehiculo,
                            'status' => $responseRubros->status(),
                            'body' => $responseRubros->body()
                        ]);
                        throw new Exception(
                            'Error al consultar rubros del vehículo en el SRI',
                            $responseRubros->status() ?: 503
                        );
                    }

                    $dataRubros = $responseRubros->json();

                    Log::channel('sri')->info('SRI: Response ConsultaRubros JSON', [
                        'placa' => $placa,
                        'codigoVehiculo' => $codigoVehiculo,
                        'body' => $dataRubros
                    ]);

                    if (!is_array($dataRubros)) {
                        $dataRubros = [];
                    }

                    $totalMatricula = 0;
                    $rubrosTransformados = [];

                    foreach ($dataRubros as $rubroReq) {
                        // ── Solo procesar rubros de MATRÍCULA ──────────────────
                        // Otros tipos (TRANSF_DOM, etc.) no son recaudación bancaria
                        // y no deben afectar el cálculo de rodaje.
                        $tipoDeuda = $rubroReq['codigoTipoDeuda'] ?? '';
                        if ($tipoDeuda !== 'MATRICULA') {
                            Log::channel('sri')->info('SRI: Rubro ignorado (no es matrícula)', [
                                'placa'        => $placa,
                                'tipoDeuda'    => $tipoDeuda,
                                'descripcion'  => $rubroReq['descripcionRubro'] ?? '',
                                'valor'        => $rubroReq['valorRubro'] ?? 0,
                            ]);
                            continue;
                        }
                        // ────────────────────────────────────────────────────────

                        $valorTotalRubro = floatval($rubroReq['valorRubro'] ?? 0);
                        $totalMatricula += $valorTotalRubro;
                        $anioHasta = intval($rubroReq['anioHastaPago'] ?? date('Y'));
                        $anioDesde = intval($rubroReq['anioDesdePago'] ?? $anioHasta);
                        $beneficiario = $rubroReq['nombreCortoBeneficiario'] ?? 'N/A';
                        $descripcion = $rubroReq['descripcionRubro'] ?? 'Rubro General';
                        $codigoRubro = $rubroReq['codigoRubro'] ?? null;
                        $detallesRubro = [];

                        if ($codigoRubro) {
                            $urlComponentes = 'https://srienlinea.sri.gob.ec/sri-matriculacion-vehicular-recaudacion-servicio-internet/rest/ConsultaComponente/obtenerListaComponentesPorCodigoConsultaRubro';
                            Log::info('SRI: Obteniendo componentes del rubro para desglose', ['codigoRubro' => $codigoRubro]);
                            try {
                                $responseComps = Http::timeout($this->timeout)->get($urlComponentes, ['codigoConsultaRubro' => $codigoRubro]);
                                if ($responseComps->successful()) {
                                    $dataComps = $responseComps->json();

                                    Log::channel('sri')->info('SRI: Response ComponentesRubro JSON', [
                                        'codigoRubro' => $codigoRubro,
                                        'body' => $dataComps
                                    ]);

                                    if (is_array($dataComps)) {
                                        foreach ($dataComps as $comp) {
                                            $detallesRubro[] = [
                                                'anio' => intval($comp['anioFiscal'] ?? $anioHasta),
                                                'valor' => floatval($comp['valorComponente'] ?? 0),
                                                'descripcionComponente' => $comp['nombreComponente'] ?? 'Mapeado',
                                            ];
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                Log::warning('SRI: Error consultando componentes del rubro', [
                                    'codigoRubro' => $codigoRubro,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }

                        if (empty($detallesRubro)) {
                            $detallesRubro[] = [
                                'anio' => $anioHasta,
                                'valor' => $valorTotalRubro,
                                'descripcionComponente' => 'Mapeado',
                            ];
                        }

                        $rubrosTransformados[] = [
                            'descripcion' => $descripcion,
                            'valor' => $valorTotalRubro,
                            'beneficiario' => $beneficiario,
                            'periodoFiscal' => (string) $anioHasta,
                            'detallesRubro' => $detallesRubro
                        ];
                    }

                    // ── Detectar si el SRI indica que ya está al día ──────────
                    // Si 'ultimoAnioPagado' == año actual Y no hay rubros de matrícula,
                    // marcamos explícitamente para que el método principal vaya al
                    // historial sin crear un registro ambiguo de $0.00 con método 'deuda'.
                    $anioActualLocal = (int) date('Y');
                    $ultimoAnioPagado = intval($dataVehiculo['ultimoAnioPagado'] ?? 0);
                    $yaAlDia = ($ultimoAnioPagado === $anioActualLocal && count($rubrosTransformados) === 0);

                    if ($yaAlDia) {
                        Log::info('SRI: Vehículo ya pagó el año actual según ultimoAnioPagado, redirigiendo a historial', [
                            'placa' => $placa,
                            'ultimoAnioPagado' => $ultimoAnioPagado,
                        ]);
                    }

                    $data = [
                        'placa' => $dataVehiculo['numeroPlaca'] ?? $placa,
                        'marca' => $dataVehiculo['descripcionMarca'] ?? '',
                        'modelo' => $dataVehiculo['descripcionModelo'] ?? '',
                        'anioModelo' => $dataVehiculo['anioAuto'] ?? 0,
                        'clase' => $dataVehiculo['nombreClase'] ?? '',
                        'cilindraje' => $dataVehiculo['cilindraje'] ?? 0,
                        'total' => $totalMatricula,
                        'ya_al_dia' => $yaAlDia,  // ← señal para el método principal
                        // 'deudas' vacío = no debe nada (deuda ya pagada o sin rubros)
                        'deudas' => [],
                    ];

                    if (count($rubrosTransformados) > 0) {
                        $data['deudas'] = [
                            [
                                'descripcion' => 'PAGO DEL VALOR DE LA MATRÍCULA',
                                'subtotal' => $totalMatricula,
                                'rubros' => $rubrosTransformados
                            ]
                        ];
                    }

                    if ($this->cacheEnabled) {
                        Cache::put($cacheKey, $data, $this->cacheTtl);
                    }

                    return $data;
                });

            } catch (\Throwable $e) {
                $codigo = (int) $e->getCode();
                $ultimoError = $e;

                // ── Error definitivo (404): placa no existe, no reintentar ──
                if ($codigo === 404) {
                    Log::info('SRI: Placa no encontrada (404), sin reintentos', ['placa' => $placa]);
                    throw $e;
                }

                // ── Error transitorio (5xx, red, timeout): reintentar ──
                $esUltimoIntento = ($intento === $intentosTotales);
                if ($esUltimoIntento) {
                    Log::error('SRI: Agotados todos los reintentos', [
                        'placa' => $placa,
                        'intentos' => $intento,
                        'error' => $e->getMessage(),
                    ]);
                    // Relanzar con código 503 para que el llamador sepa que fue fallo del SRI
                    throw new Exception(
                        'El servicio SRI no está disponible temporalmente. Intente nuevamente en unos minutos.',
                        503
                    );
                }

                Log::warning('SRI: Fallo transitorio, reintentando', [
                    'placa' => $placa,
                    'intento' => $intento,
                    'de' => $intentosTotales,
                    'error' => $e->getMessage(),
                    'pausa_ms' => $this->retryDelayMs,
                ]);

                // Pausa antes del siguiente intento
                usleep($this->retryDelayMs * 1000);
            }
        }

        throw $ultimoError ?? new Exception('Error desconocido al consultar el SRI', 503);
    }


    /**
     * Extraer rubros del detalle completo
     */
    private function extraerRubros(array $detalle): array
    {
        $rubros = [];

        // Si viene del endpoint de detalles de pago (ya pagado)
        if (isset($detalle['desde_pago']) && $detalle['desde_pago'] === true) {
            foreach ($detalle['rubros_raw'] as $item) {
                $rubros[] = [
                    'descripcion' => $item['descripcionRubro'] ?? 'Sin descripción',
                    'valor' => floatval(str_replace(',', '.', $item['valor'] ?? 0)),
                    'beneficiario' => $this->mapearBeneficiario($item['descripcionRubro'] ?? ''),
                    'periodoFiscal' => (string) ($item['anio'] ?? ''),
                ];
            }
            return $rubros;
        }

        if (isset($detalle['deudas']) && is_array($detalle['deudas'])) {
            foreach ($detalle['deudas'] as $deuda) {
                if (isset($deuda['rubros']) && is_array($deuda['rubros'])) {
                    foreach ($deuda['rubros'] as $rubro) {
                        $rubros[] = [
                            'descripcion' => $rubro['descripcion'] ?? 'Sin descripción',
                            'valor' => floatval($rubro['valor'] ?? 0),
                            'beneficiario' => $rubro['beneficiario'] ?? 'N/A',
                            'periodoFiscal' => $rubro['periodoFiscal'] ?? '',
                        ];
                    }
                }
            }
        }

        Log::info('SRI: Rubros extraídos', ['cantidad' => count($rubros)]);

        return $rubros;
    }

    /**
     * Mapear beneficiario basado en la descripción del rubro
     */
    private function mapearBeneficiario(string $descripcion): string
    {
        $descripcion = strtoupper($descripcion);
        if (str_contains($descripcion, 'SPPAT'))
            return 'SPPAT';
        if (str_contains($descripcion, 'PROPIEDAD') || str_contains($descripcion, 'IMPUESTO'))
            return 'SRI';
        if (str_contains($descripcion, 'ANT') || str_contains($descripcion, 'TASAS'))
            return 'ANT';
        return 'N/A';
    }

    /**
     * Wrapper para trackear requests al SRI
     */
    private function trackRequest(string $endpoint, string $placa, bool $wasCached, callable $callback)
    {
        $start = microtime(true);
        $success = false;
        $statusCode = null;
        $errorType = null;
        $errorMessage = null;

        try {
            $result = $callback();
            $success = true;
            $statusCode = 200;
            return $result;

        } catch (ConnectionException $e) {
            $errorType = 'network';
            $errorMessage = 'Error de conexión con el SRI';
            $statusCode = 0;
            throw $e;

        } catch (HttpRequestException $e) {
            $statusCode = $e->response?->status() ?? 0;
            $errorType = $statusCode >= 500 ? 'server_error' : 'client_error';
            $errorMessage = $e->getMessage();
            throw $e;

        } catch (\Exception $e) {
            $errorType = 'unknown';
            $errorMessage = $e->getMessage();
            throw $e;

        } finally {
            $duration = (microtime(true) - $start) * 1000;

            // Registrar en BD de forma asíncrona (no bloquea la respuesta)
            try {
                dispatch(function () use ($placa, $endpoint, $statusCode, $duration, $success, $errorType, $errorMessage, $wasCached) {
                    try {
                        SriRequest::create([
                            'placa' => $placa,
                            'endpoint' => $endpoint,
                            'status_code' => $statusCode,
                            'duration_ms' => round($duration),
                            'success' => $success,
                            'error_type' => $errorType,
                            'error_message' => $errorMessage ? substr($errorMessage, 0, 500) : null,
                            'cached' => $wasCached,
                        ]);
                    } catch (\Throwable $e) {
                        // Si el tracking falla, intentar logear pero no romper la petición
                        try {
                            Log::warning('Failed to save SRI request record', ['error' => $e->getMessage()]);
                        } catch (\Throwable $logError) {
                        }
                    }
                });
            } catch (\Throwable $e) {
                try {
                    Log::warning('Failed to dispatch SRI tracking job', ['error' => $e->getMessage()]);
                } catch (\Throwable $logError) {
                }
            }

            // Log crítico si falla
            if (!$success) {
                try {
                    Log::channel('sri')->error('SRI Request Failed', [
                        'placa' => $placa,
                        'endpoint' => $endpoint,
                        'error_type' => $errorType,
                        'duration' => round($duration),
                        'status' => $statusCode,
                    ]);
                } catch (\Throwable $e) {
                    try {
                        // Si el canal 'sri' no existe, usar el log por defecto
                        Log::error('SRI Request Failed (fallback)', [
                            'placa' => $placa,
                            'endpoint' => $endpoint,
                            'error_type' => $errorType,
                            'duration' => round($duration),
                            'status' => $statusCode,
                            'note' => 'Log channel [sri] is not defined or failing'
                        ]);
                    } catch (\Throwable $logError) {
                        // Si incluso el log por defecto falla (error de permisos),
                        // no hacemos nada para no romper la petición principal.
                    }
                }
            }
        }
    }
    /**
     * Calcular impuesto (10% del valor de matrícula con mín/máx)
     */
    public function calcularImpuesto(float $valorMatricula): float
    {
        $impuesto = $valorMatricula * 0.10;

        // Aplicar límites
        $impuesto = max(10.00, min(100.00, $impuesto));

        return round($impuesto, 2);
    }

    /**
     * Obtener historial de pagos desde el SRI
     */
    public function obtenerHistorialPagos(string $placa): array
    {
        $url = "https://srienlinea.sri.gob.ec/sri-matriculacion-vehicular-recaudacion-servicio-internet/rest/consultaPagos/obtenerPorPlacaCampvCpn";

        try {
            Log::info('SRI: Consultando historial de pagos', ['placa' => $placa]);

            $response = Http::timeout($this->timeout)->get($url, [
                'placaCampvCpn' => $placa
            ]);

            if (!$response->successful()) {
                Log::error('SRI: Error al obtener historial de pagos', [
                    'placa' => $placa,
                    'status' => $response->status()
                ]);
                return [];
            }

            $data = $response->json();

            Log::channel('sri')->info('SRI: Response HistorialPagos JSON', [
                'placa' => $placa,
                'body' => $data
            ]);

            return $data['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('SRI: Excepción al obtener historial de pagos', [
                'placa' => $placa,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener el último pago de matrícula del historial
     */
    public function obtenerUltimoPagoMatricula(string $placa): ?array
    {
        $pagos = $this->obtenerHistorialPagos($placa);

        if (empty($pagos)) {
            return null;
        }

        // Filtrar por tipoDeuda: "PAGO DEL VALOR DE LA MATRÍCULA"
        $pagosMatricula = array_filter($pagos, function ($pago) {
            return ($pago['tipoDeuda'] ?? '') === 'PAGO DEL VALOR DE LA MATRÍCULA';
        });

        if (empty($pagosMatricula)) {
            return null;
        }

        // Ordenar por fecha de pago descendente
        usort($pagosMatricula, function ($a, $b) {
            return strcmp($b['fechaDePago'] ?? '', $a['fechaDePago'] ?? '');
        });

        return reset($pagosMatricula);
    }

    /**
     * Obtener detalles de un pago específico por codigoRecaudacion
     */
    public function obtenerDetallesPago(int $codigoRecaudacion): array
    {
        $url = "https://srienlinea.sri.gob.ec/sri-matriculacion-vehicular-recaudacion-servicio-internet/rest/consultaPagos/obtenerDetallesPago";

        try {
            Log::info('SRI: Consultando detalles del pago', ['codigoRecaudacion' => $codigoRecaudacion]);

            $response = Http::timeout($this->timeout)->get($url, [
                'codigoRecaudacion' => $codigoRecaudacion
            ]);

            if (!$response->successful()) {
                Log::error('SRI: Error al obtener detalles de pago', [
                    'codigoRecaudacion' => $codigoRecaudacion,
                    'status' => $response->status()
                ]);
                return [];
            }

            $data = $response->json();

            Log::channel('sri')->info('SRI: Response DetallesPago JSON', [
                'codigoRecaudacion' => $codigoRecaudacion,
                'body' => $data
            ]);

            return $data['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('SRI: Excepción al obtener detalles de pago', [
                'codigoRecaudacion' => $codigoRecaudacion,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Calcular desglose de rodaje y mora por año fiscal.
     *
     * Agrupa los detallesRubro por año excluyendo RECARGO e INTERES
     * (pertenecen a otras entidades), calcula:
     *   - Rodaje por año: 10% del subtotal (mín $10, máx $100)
     *   - Mora por año: 10% del rodaje × años de atraso
     *
     * @param array $detalle Respuesta completa del SRI
     * @return array Desglose por año con totales
     */
    public function calcularDesglosePorAnio(array $detalle, ?int $anioActualSimulado = null): array
    {
        $anioActual = $anioActualSimulado ?? intval(date('Y'));
        $valoresPorAnio = [];
        $atrasosDetectados = []; // Para marcar años que tuvieron recargos/intereses

        // Recorrer deudas → rubros → detallesRubro
        if (isset($detalle['desde_pago']) && $detalle['desde_pago'] === true) {
            foreach ($detalle['rubros_raw'] as $item) {
                $anio = intval($item['anio'] ?? 0);
                $valor = floatval(str_replace(',', '.', $item['valor'] ?? 0));
                $comp = strtoupper($item['descripcionComponente'] ?? '');

                if ($anio <= 0)
                    continue;

                if (!isset($valoresPorAnio[$anio])) {
                    $valoresPorAnio[$anio] = 0;
                }

                // Sumar todos los componentes al subtotal del año
                $valoresPorAnio[$anio] += $valor;

                if (($comp === 'RECARGO' || $comp === 'INTERES') && $valor > 0) {
                    // Si hay un recargo o interés positivo, marcamos que hubo atraso en ese año original
                    $atrasosDetectados[$anio] = true;
                }
            }
        } else if (isset($detalle['deudas']) && is_array($detalle['deudas'])) {
            foreach ($detalle['deudas'] as $deuda) {
                if (!isset($deuda['rubros']) || !is_array($deuda['rubros'])) {
                    continue;
                }
                foreach ($deuda['rubros'] as $rubro) {
                    if (!isset($rubro['detallesRubro']) || !is_array($rubro['detallesRubro'])) {
                        continue;
                    }
                    foreach ($rubro['detallesRubro'] as $detalleRubro) {
                        $anio = intval($detalleRubro['anio'] ?? 0);
                        $valor = floatval($detalleRubro['valor'] ?? 0);

                        // Permitir valores negativos (como PRESCRIPCION) para que se resten del subtotal del año
                        if ($anio > 0) {
                            if (!isset($valoresPorAnio[$anio])) {
                                $valoresPorAnio[$anio] = 0;
                            }
                            $valoresPorAnio[$anio] += $valor;
                        }
                    }
                }
            }
        }

        // Ordenar por año descendente (más reciente primero)
        krsort($valoresPorAnio);

        // ─── NUEVO CÁLCULO: Rodaje con topes POR AÑO (Mín $10, Máx $100) ──────
        $desglose = [];
        $totalRodaje = 0;
        $totalMora = 0;

        foreach ($valoresPorAnio as $anio => $subtotalMatricula) {
            // 1. Calcular rodaje base (10%)
            $rodajeBase = $subtotalMatricula * 0.10;

            // 2. Aplicar límites POR AÑO: Mín $10.00, Máx $100.00
            $rodajeAnual = max(10.00, min(100.00, $rodajeBase));
            $rodajeAnual = round($rodajeAnual, 2);

            // CORRECCION: Años de atraso y mora
            // Si leemos desde el historial (ya pagado), no debemos calcular mora artificial
            // basándonos en el AÑO ACTUAL, sino sólo en si el historial indicaba un atraso real.
            $aniosAtraso = 0;
            $mora = 0;

            if (isset($detalle['desde_pago']) && $detalle['desde_pago'] === true) {
                // Para comprobantes pagados, hay mora si:
                // (a) el comprobante incluyó recargos/intereses explícitos en ese año, O
                // (b) el año del rubro es anterior al año en que se realizó el pago
                $anioPago = intval($detalle['anio_pago'] ?? $anioActual);
                $tieneAtrasoExplicito = isset($atrasosDetectados[$anio]);
                $anioRubroEsAnterior  = ($anio < $anioPago);

                if ($tieneAtrasoExplicito || $anioRubroEsAnterior) {
                    // Solo aplicar mora si el año es realmente un año pasado (no el año de emisión)
                    if ($anio < $anioActual) {
                        $aniosAtraso = 1;
                        $mora = round($rodajeAnual * 0.10, 2);
                    } else {
                        // Año actual con recargo administrativo de la ANT: sin mora de rodaje
                        $aniosAtraso = 0;
                        $mora = 0;
                    }
                }
            } else {
                // Cálculo vivo de deuda: Mora base según la diferencia real con el año en curso
                $aniosAtraso = max(0, $anioActual - $anio);
                $mora = round($rodajeAnual * 0.10 * $aniosAtraso, 2);
            }

            $valorAnio = round($rodajeAnual + $mora, 2);

            $desglose[] = [
                'anio' => $anio,
                'subtotal_matricula' => round($subtotalMatricula, 2),
                'rodaje' => $rodajeAnual,
                'anios_atraso' => $aniosAtraso,
                'mora' => $mora,
                'valor' => $valorAnio,
            ];

            $totalRodaje += $rodajeAnual;
            $totalMora += $mora;
        }

        // El total a pagar es la suma de los rodajes anuales + mora total
        $totalPagar = round($totalRodaje + $totalMora, 2);

        Log::info('SRI: Desglose por año calculado con límites anuales', [
            'anios' => count($desglose),
            'total_rodaje' => round($totalRodaje, 2),
            'total_mora' => round($totalMora, 2),
            'total_a_pagar' => $totalPagar,
        ]);

        return [
            'desglose' => $desglose,
            'total_rodaje' => round($totalRodaje, 2),
            'total_mora' => round($totalMora, 2),
            'total_a_pagar' => $totalPagar,
        ];
    }

    /**
     * Consultar vehículo completo (placa, valores, rubros, impuesto)
     * Ahora usa una sola llamada al SRI
     */
    public function consultarVehiculoCompleto(string $placa, ?int $anioActualSimulado = null): array
    {
        $placa = strtoupper($placa);
        $cacheKey = "sri_full_v3_{$placa}";

        // Comprobación rápida inicial de caché
        if ($this->cacheEnabled && Cache::has($cacheKey)) {
            Log::info('SRI: Retornando datos desde el caché local', ['placa' => $placa]);
            return Cache::get($cacheKey);
        }

        // ─── Atomic Lock por Placa ─────────────────────────────
        // Evita que múltiples consultas por el mismo vehículo lancen llamadas paralelas al SRI.
        return Cache::lock("sri_lock_v1_{$placa}", 30)->block(15, function () use ($placa, $cacheKey, $anioActualSimulado) {
            
            // ─── Double-Checked Locking ──────────────────────────────
            if ($this->cacheEnabled && Cache::has($cacheKey)) {
                Log::info('SRI: Retornando datos desde el caché (tras esperar bloqueo)', ['placa' => $placa]);
                return Cache::get($cacheKey);
            }

            Log::info('SRI: Iniciando consulta completa', ['placa' => $placa]);

            try {
                // ─────────────────────────────────────────────────────────────────────
                // 1. Intentar obtener el detalle del SRI (con reintentos internos)
                // ─────────────────────────────────────────────────────────────────────
                $detalleBase = null;
                $errorTransitorio = false; // true = fallo de red/5xx tras reintentos

                try {
                    $detalleBase = $this->obtenerDetalleCompleto($placa);
                } catch (Exception $e) {
                    $codigo = (int) $e->getCode();

                    if ($codigo === 404) {
                        // Placa genuinamente no existe → propagar inmediatamente
                        throw $e;
                    }

                    // Para cualquier otro error (503, timeout) NO vamos al historial automáticamente.
                    $errorTransitorio = true;
                    Log::warning('SRI: Fallo en endpoint de detalle (error transitorio)', [
                        'placa' => $placa,
                        'codigo' => $codigo,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Si el endpoint de detalle falló por causa transitoria, lanzamos error claro.
                if ($errorTransitorio) {
                    throw new Exception(
                        'El servicio del SRI no está disponible en este momento. Por favor, intente nuevamente en unos minutos.',
                        503
                    );
                }

                // ─────────────────────────────────────────────────────────────────────
                // 2. Decidir qué datos usar
                //    ✔ Tiene deudas activas              → usar deudas directamente
                //    ✔ ya_al_dia=true (SRI lo confirmó) → ir al historial directamente
                //    ✔ Sin deudas y sin ya_al_dia        → intentar historial igual
                // ─────────────────────────────────────────────────────────────────────
                $yaAlDia = $detalleBase['ya_al_dia'] ?? false;

                if ($detalleBase && isset($detalleBase['deudas']) && !empty($detalleBase['deudas'])) {
                    // ── Caso 1: Tiene deudas activas de matrícula ──
                    $detalle = $detalleBase;
                    $detalle['metodo_utilizado'] = 'deuda';
                } else {
                    // ── Caso 2: Sin deudas → ir al historial ──────
                    // Si ya_al_dia=true el SRI confirmó explícitamente que está al día.
                    $motivo = $yaAlDia ? 'SRI confirma ultimoAnioPagado=año actual' : 'Sin rubros activos encontrados';
                    Log::info("SRI: Redirigiendo a historial de pagos ({$motivo})", ['placa' => $placa]);

                    $ultimoPago = $this->obtenerUltimoPagoMatricula($placa);
                    if (!$ultimoPago) {
                        if ($detalleBase && !$yaAlDia) {
                            // Solo usamos base con método 'deuda' si el SRI NO confirmó que está al día
                            // (evita el registro engañoso de $0 con método='deuda')
                            $detalle = $detalleBase;
                            $detalle['metodo_utilizado'] = 'deuda';
                        } else {
                            throw new Exception('No se encontró información ni historial de pagos para el vehículo');
                        }
                    } else {
                        $codigoRecaudacion = $ultimoPago['codigoRecaudacion'];
                        $detallesPago = $this->obtenerDetallesPago($codigoRecaudacion);

                        if (empty($detallesPago)) {
                            throw new Exception('El vehículo registra pagos pero no se pudo obtener el detalle histórico.');
                        }

                        $detalle = $detalleBase ?? [
                            'placa' => $placa,
                            'marca' => 'VEHÍCULO',
                            'modelo' => '',
                            'anioModelo' => 0,
                            'clase' => '',
                            'cilindraje' => 0,
                        ];

                        $detalle['desde_pago'] = true;
                        $detalle['total'] = floatval($ultimoPago['monto'] ?? 0);
                        $detalle['anio_pago'] = intval(substr($ultimoPago['fechaDePago'] ?? date('Y'), 0, 4));
                        $detalle['rubros_raw'] = $detallesPago;
                        $detalle['metodo_utilizado'] = 'historial';
                    }
                }

                // Extraer información
                $valorMatricula = round(floatval($detalle['total'] ?? 0), 2);
                $impuesto = $this->calcularImpuesto($valorMatricula);
                $rubros = $this->extraerRubros($detalle);

                // Calcular desglose y totales reales
                $desgloseResult = $this->calcularDesglosePorAnio($detalle, $anioActualSimulado);

                Log::info('SRI: Consulta completada exitosamente', [
                    'placa' => $placa,
                    'valor_matricula' => $valorMatricula,
                    'total_rodaje' => $desgloseResult['total_rodaje'],
                    'total_a_pagar' => $desgloseResult['total_a_pagar'],
                    'rubros' => count($rubros)
                ]);

                $resultado = [
                    'vehiculo' => [
                        'placa' => $detalle['placa'] ?? $placa,
                        'marca' => $detalle['marca'] ?? '',
                        'modelo' => $detalle['modelo'] ?? '',
                        'anio' => intval($detalle['anioModelo'] ?? 0),
                        'clase' => $detalle['clase'] ?? '',
                        'cilindraje' => $detalle['cilindraje'] ?? 0,
                        'descripcion_completa' => trim(
                            ($detalle['marca'] ?? '') . ' ' .
                            ($detalle['modelo'] ?? '') . ' ' .
                            ($detalle['anioModelo'] ?? '')
                        ),
                    ],
                    'valor_matricula' => $valorMatricula,
                    'impuesto' => $desgloseResult['total_rodaje'],
                    'desglose_anual' => $desgloseResult['desglose'],
                    'totales' => [
                        'total_rodaje' => $desgloseResult['total_rodaje'],
                        'total_mora' => $desgloseResult['total_mora'],
                        'total_a_pagar' => $desgloseResult['total_a_pagar'],
                    ],
                    'total_a_pagar' => $desgloseResult['total_a_pagar'],
                    'metodo_sri' => $detalle['metodo_utilizado'] ?? 'deuda',
                ];

                // Guardar en caché si está habilitado
                if ($this->cacheEnabled) {
                    Cache::put($cacheKey, $resultado, $this->cacheTtl);
                }

                return $resultado;

            } catch (\Throwable $e) {
                try {
                    Log::error('SRI: Error en consulta completa', [
                        'placa' => $placa,
                        'error' => $e->getMessage()
                    ]);
                } catch (\Throwable $logError) {}
                throw $e;
            }
        });
    }
}
