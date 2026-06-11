<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pago extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehiculo_id',
        'placa',
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

            // Incrementar monto total recaudado
            \Illuminate\Support\Facades\Redis::incrbyfloat('stats:recaudacion:total', $monto);

            // Incrementar monto recaudado del día
            \Illuminate\Support\Facades\Redis::incrbyfloat("stats:recaudacion:dia:{$fecha}", $monto);

            // Incrementar contador de pagos completados del día
            \Illuminate\Support\Facades\Redis::incr("stats:pagos:completados:dia:{$fecha}");

            // Incrementar contador total de pagos completados
            \Illuminate\Support\Facades\Redis::incr('stats:pagos:completados:total');

            // Expirar contador del día después de 60 días
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
