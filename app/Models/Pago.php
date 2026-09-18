<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * HISTÓRICO — solo lectura desde la reestructuración cabecera/detalle.
 * Apunta a `pagos_legacy` (la vieja `pagos`, renombrada, con sus 16 filas
 * intactas). Nada vuelve a escribir aquí: los pagos nuevos (bancarios y
 * de pasarela ciudadana) usan App\Models\TransaccionPago + PagoDetalle.
 * Se conserva para poder auditar/consultar lo registrado antes de este
 * cambio (certificados ya entregados, referencias ya emitidas).
 *
 * RECORDATORIO: varios registros de `pagos_legacy` son datos de prueba de
 * rondas de QA anteriores, no actividad real de bancos/cooperativas
 * (confirmado por auditoría — ver commit de esta reestructuración). En
 * particular, `datos_adicionales->entidad_recaudadora` en varias filas
 * (ids 8,10,11,12,13,15,17,19 — referencias TXN-COOP-00X) tiene nombres como
 * "Cooperativa Visandes" o "Cooperativa Cotopaxense" escritos como texto
 * libre durante pruebas — no corresponden a ninguna entidad realmente
 * registrada en `api_tokens`. Si alguien los ve en un reporte histórico,
 * no son evidencia de actividad externa real.
 */
class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos_legacy';

    protected static function boot()
    {
        parent::boot();

        // certificado_token identifica al pago en /certificado/{token} (acceso
        // público al Certificado de Pago). No reutiliza referencia_pago porque
        // esa la define el banco/cooperativa externo y suele ser secuencial
        // (ej. TXN-COOP-001) — no apta como clave de acceso no adivinable.
        static::creating(function (Pago $pago) {
            if (empty($pago->certificado_token)) {
                $pago->certificado_token = Str::random(32);
            }
        });
    }

    protected $fillable = [
        'vehiculo_id',
        'placa',
        'api_token_id', // ← entidad que registró el pago
        'consulta_bancaria_id', // ← consulta que originó el pago
        'monto_impuesto',
        'monto_total',
        'estado',
        'referencia_pago',
        'link_pago',
        'fecha_pago',
        'anio_fiscal',
        'datos_adicionales',
        'datos_facturacion',
    ];

    protected $casts = [
        'monto_impuesto' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'fecha_pago' => 'datetime',
        'datos_adicionales' => 'array',
        'datos_facturacion' => 'array',
        'anio_fiscal' => 'integer',
    ];

    /**
     * Relación con vehículo
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /**
     * Relación con la entidad bancaria que registró el pago
     */
    public function entidadBancaria(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ApiToken::class, 'api_token_id', 'id');
    }

    /**
     * Alias para consultas admin (whereHas)
     */
    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ApiToken::class, 'api_token_id', 'id');
    }

    /**
     * Relación con la consulta que originó el pago
     */
    public function consultaBancaria(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ConsultaBancaria::class, 'consulta_bancaria_id', 'id');
    }

    /**
     * Relación con transacciones
     */
    public function transacciones(): HasMany
    {
        return $this->hasMany(Transaccion::class);
    }

    /**
     * Marcar como pagado
     */
    public function marcarComoPagado(string $referencia): void
    {
        $this->update([
            'estado' => 'pagado',
            'referencia_pago' => $referencia,
            'fecha_pago' => now(),
        ]);

        // Registrar estadísticas de recaudación en Redis
        $this->registrarEstadisticasRecaudacion();
    }

    /**
     * Registrar estadísticas de recaudación en Redis
     */
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

    /**
     * Marcar como fallido
     */
    public function marcarComoFallido(): void
    {
        $this->update([
            'estado' => 'fallido',
        ]);
    }

    /**
     * Marcar como expirado
     */
    public function marcarComoExpirado(): void
    {
        $this->update([
            'estado' => 'expirado',
        ]);
    }

    /**
     * Verificar si está pagado
     */
    public function estaPagado(): bool
    {
        return $this->estado === 'pagado';
    }

    /**
     * Verificar si está pendiente
     */
    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    /**
     * Scope para obtener pagos del año actual
     */
    public function scopeDelAnioActual($query)
    {
        return $query->where('anio_fiscal', date('Y'));
    }

    /**
     * Scope para obtener solo pagados
     */
    public function scopePagados($query)
    {
        return $query->where('estado', 'pagado');
    }

    /**
     * Scope para obtener pendientes
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }
}
