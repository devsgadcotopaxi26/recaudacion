<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Cabecera de una operación real de cobro (estilo factura): una fila por
 * transacción, sin importar cuántos años fiscales cubra ni por qué canal
 * entró (API bancaria o pasarela ciudadana). Reemplaza a Pago (ahora
 * histórico en `pagos_legacy`) como fuente de verdad hacia adelante.
 */
class TransaccionPago extends Model
{
    use HasFactory;

    protected $table = 'transacciones_pago';

    protected static function boot()
    {
        parent::boot();

        // Mismo criterio que tenía Pago::certificado_token: acceso público
        // al Certificado de Pago vía /certificado/{token}, no adivinable.
        // No reutiliza referencia_externa porque esa la define el
        // banco/cooperativa/pasarela externa.
        static::creating(function (TransaccionPago $transaccion) {
            if (empty($transaccion->certificado_token)) {
                $transaccion->certificado_token = Str::random(32);
            }

            // Identificador de verificación de bajo privilegio (ver
            // create_transacciones_pago_table, columna codigo_transaccion):
            // separado de certificado_token a propósito — este es para el
            // caso "verificar autenticidad de un comprobante impreso/QR"
            // (respuesta mínima, sin PII), certificado_token es para el
            // certificado completo. Formato corto (TRX-XXXXXX) en vez de
            // random(32): pensado para tipearse a mano si hace falta,
            // no solo para escanear QR.
            if (empty($transaccion->codigo_transaccion)) {
                $transaccion->codigo_transaccion = self::generarCodigoTransaccion();
            }
        });
    }

    /**
     * Alfabeto sin caracteres ambiguos al tipear a mano: sin 0/O, 1/I/L.
     */
    private const ALFABETO_CODIGO_TRANSACCION = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Genera un código "TRX-XXXXXX" (6 caracteres del alfabeto sin
     * ambiguos) y reintenta si por casualidad ya existe — 31^6 (~887
     * millones de combinaciones) hace la colisión extremadamente
     * improbable, pero la restricción UNIQUE de la columna es la garantía
     * real; esto solo evita depender de capturar la excepción de la BD
     * en el camino feliz.
     */
    private static function generarCodigoTransaccion(): string
    {
        $alfabeto = self::ALFABETO_CODIGO_TRANSACCION;
        $largo = strlen($alfabeto);

        do {
            $codigo = 'TRX-';
            for ($i = 0; $i < 6; $i++) {
                $codigo .= $alfabeto[random_int(0, $largo - 1)];
            }
        } while (self::where('codigo_transaccion', $codigo)->exists());

        return $codigo;
    }

    /**
     * El exists() de generarCodigoTransaccion() y el INSERT real son dos
     * pasos separados — ventana de carrera (TOCTOU) teórica: dos requests
     * concurrentes podrían generar el mismo código, pasar ambos el
     * exists() (ninguno ve al otro todavía) y el segundo INSERT violaría
     * el UNIQUE de la columna. La BD ya garantiza que nunca se persiste
     * un duplicado (eso no cambia); esto solo evita que ese caso le
     * devuelva un 500 al banco — se regenera el código y se reintenta el
     * insert una vez más antes de rendirse. Extremadamente improbable
     * (31^6 ≈ 887M combinaciones), por eso el límite de reintentos es
     * bajo (1) en vez de un loop sin fin.
     */
    public function save(array $options = [])
    {
        $intentos = 0;
        $maxIntentos = 2; // intento inicial + 1 reintento

        while (true) {
            try {
                return parent::save($options);
            } catch (QueryException $e) {
                $intentos++;

                $esColisionCodigoTransaccion = !$this->exists
                    && $e->getCode() === '23000'
                    && str_contains($e->getMessage(), 'codigo_transaccion');

                if (!$esColisionCodigoTransaccion || $intentos >= $maxIntentos) {
                    throw $e;
                }

                $this->codigo_transaccion = self::generarCodigoTransaccion();
            }
        }
    }

    protected $fillable = [
        'placa',
        'canal',
        'referencia_externa',
        'codigo_consulta',
        'consulta_bancaria_id',
        'api_token_id',
        'monto_total',
        'estado',
        'fecha_pago',
        'certificado_token',
        'codigo_transaccion',
        'link_pago',
        'datos_facturacion',
        'datos_adicionales',
        'revertido_en',
        'revertido_motivo',
        'revertido_por_api_token_id',
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
        'fecha_pago' => 'datetime',
        'datos_facturacion' => 'array',
        'datos_adicionales' => 'array',
        'revertido_en' => 'datetime',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(PagoDetalle::class, 'transaccion_pago_id');
    }

    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'api_token_id', 'id');
    }

    public function consultaBancaria(): BelongsTo
    {
        return $this->belongsTo(ConsultaBancaria::class, 'consulta_bancaria_id', 'id');
    }

    /**
     * Nombre de la entidad para mostrar en reportes/certificados. Sin
     * columna propia: se resuelve siempre vía la FK api_token_id (fuente
     * de verdad real, ya no hay texto libre duplicado). Canal
     * 'pasarela_ciudadana' no tiene api_token_id (el ciudadano paga
     * directo, sin entidad bancaria de por medio) — valor explícito en
     * vez de dejar pasar null sin manejar.
     */
    public function nombreEntidad(): string
    {
        if ($this->canal === 'pasarela_ciudadana') {
            return 'Pago en línea';
        }

        return $this->apiToken?->entidad_nombre ?? 'Entidad desconocida';
    }

    /**
     * Log técnico de llamadas a la pasarela (api_call/webhook/callback)
     * asociadas a esta transacción — concepto DISTINTO, ver Transaccion.
     */
    public function llamadasPasarela(): HasMany
    {
        return $this->hasMany(Transaccion::class, 'transaccion_pago_id');
    }

    /**
     * Comprobante único por transacción (PAG-XXXXXX), no por año — coincide
     * con lo que el ciudadano recibe en la vida real en ventanilla.
     */
    public function comprobante(): string
    {
        return 'PAG-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Marcar como pagado (flujo pasarela ciudadana: pendiente → pagado vía
     * webhook). Propaga el estado a los detalles (denormalizado) y
     * registra estadísticas de recaudación, igual que hacía
     * Pago::marcarComoPagado().
     */
    public function marcarComoPagado(string $referencia): void
    {
        $this->update([
            'estado' => 'pagado',
            'referencia_externa' => $referencia,
            'fecha_pago' => now(),
        ]);

        $this->detalles()->update(['estado' => 'pagado']);

        $this->registrarEstadisticasRecaudacion();

        // Invalida el caché de "deuda pendiente" (SriVehiculoService::
        // consultarVehiculoCompleto(), clave sri_full_v3_{placa}) — ese
        // caché incorpora una consulta a PagoDetalle local, así que sin
        // esto la consulta pública seguiría mostrando deuda ya pagada
        // hasta que expire el TTL corto. NO se invalida sri:detalle:{placa}
        // (dato crudo del SRI, no cambia porque este sistema registró un
        // pago) — evita una llamada real al SRI innecesaria.
        \Illuminate\Support\Facades\Cache::forget("sri_full_v3_{$this->placa}");
    }

    public function marcarComoFallido(): void
    {
        $this->update(['estado' => 'fallido']);
        $this->detalles()->update(['estado' => 'fallido']);
    }

    public function marcarComoExpirado(): void
    {
        $this->update(['estado' => 'expirado']);
        $this->detalles()->update(['estado' => 'expirado']);
    }

    public function estaPagado(): bool
    {
        return $this->estado === 'pagado';
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    /**
     * Ventana de reversión bancaria: solo dentro de las 24h posteriores al
     * registro (created_at, nunca fecha_pago — mismo criterio ya usado para
     * la ventana de codigo_consulta, ver BancaController::registrarPago()).
     * No basta con 'pagado': un pago ya reversado, fallido o expirado
     * tampoco puede revertirse de nuevo.
     */
    public function puedeRevertirse(): bool
    {
        // $absolute=true explícito: desde Carbon 3, diffInHours() ya NO es
        // absoluto por defecto — sin esto, now()->diffInHours(pasado) da
        // NEGATIVO (ej. -25 para un pago de hace 25h), y "-25 <= 24" es
        // SIEMPRE true, permitiendo revertir sin límite de tiempo real.
        return $this->estado === 'pagado'
            && now()->diffInHours($this->created_at, true) <= 24;
    }

    /**
     * Revierte un pago bancario completo (cabecera + todos sus detalles,
     * nunca parcial — regla de negocio). Mismo patrón de propagación que
     * PaymentGatewayService::procesarWebhook() usa para el canal
     * pasarela_ciudadana (estado='reversado' en cabecera y detalles),
     * aquí con metadata de auditoría adicional porque el canal bancario
     * exige saber quién/cuándo/por qué (la pasarela se reversa por webhook
     * automático, sin un banco identificable detrás).
     *
     * No valida aquí si puedeRevertirse() — el llamador (BancaController)
     * ya debe haberlo chequeado para devolver el error_code específico
     * (PAGO_YA_ANULADO vs. FUERA_DE_VENTANA_REVERSION); este método solo
     * ejecuta el cambio de estado de forma atómica.
     */
    public function revertir(int $apiTokenId, string $motivo): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($apiTokenId, $motivo) {
            $this->update([
                'estado' => 'reversado',
                'revertido_en' => now(),
                'revertido_motivo' => $motivo,
                'revertido_por_api_token_id' => $apiTokenId,
            ]);

            $this->detalles()->update(['estado' => 'reversado']);
        });

        // Mismo criterio que marcarComoPagado() (ver docblock de ese
        // método): sin esto, la próxima consulta de deuda seguiría
        // mostrando el año como pagado hasta que expire el TTL del caché,
        // aunque conciliarPagosLocales() ya no encuentre el detalle en
        // estado 'pagado'.
        \Illuminate\Support\Facades\Cache::forget("sri_full_v3_{$this->placa}");
    }

    private function registrarEstadisticasRecaudacion(): void
    {
        try {
            $fecha = date('Y-m-d');
            $monto = floatval($this->monto_total);

            \Illuminate\Support\Facades\Redis::incrbyfloat('stats:recaudacion:total', $monto);
            \Illuminate\Support\Facades\Redis::incrbyfloat("stats:recaudacion:dia:{$fecha}", $monto);
            \Illuminate\Support\Facades\Redis::incr("stats:pagos:completados:dia:{$fecha}");
            \Illuminate\Support\Facades\Redis::incr('stats:pagos:completados:total');
            \Illuminate\Support\Facades\Redis::expire("stats:recaudacion:dia:{$fecha}", 5184000);
            \Illuminate\Support\Facades\Redis::expire("stats:pagos:completados:dia:{$fecha}", 5184000);
        } catch (\Exception $e) {
            \Log::warning('Error al registrar estadísticas de recaudación: ' . $e->getMessage());
        }
    }
}
