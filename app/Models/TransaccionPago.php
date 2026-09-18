<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        });
    }

    protected $fillable = [
        'placa',
        'canal',
        'referencia_externa',
        'codigo_consulta',
        'consulta_bancaria_id',
        'api_token_id',
        'entidad_recaudadora',
        'monto_total',
        'estado',
        'fecha_pago',
        'certificado_token',
        'link_pago',
        'datos_facturacion',
        'datos_adicionales',
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
        'fecha_pago' => 'datetime',
        'datos_facturacion' => 'array',
        'datos_adicionales' => 'array',
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
