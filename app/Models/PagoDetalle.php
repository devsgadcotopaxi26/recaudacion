<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila por año fiscal cubierto dentro de una TransaccionPago.
 * `placa` y `estado` están desnormalizados desde la cabecera a propósito
 * (ver migración create_pago_detalles_table) para que
 * SriVehiculoService::conciliarPagosLocales() — el path más transitado
 * del sistema — no necesite JOIN.
 */
class PagoDetalle extends Model
{
    use HasFactory;

    protected $table = 'pago_detalles';

    protected $fillable = [
        'transaccion_pago_id',
        'placa',
        'estado',
        'anio_fiscal',
        'monto_impuesto',
        'monto_mora',
        'monto_total',
    ];

    protected $casts = [
        'monto_impuesto' => 'decimal:2',
        'monto_mora' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'anio_fiscal' => 'integer',
    ];

    public function transaccionPago(): BelongsTo
    {
        return $this->belongsTo(TransaccionPago::class, 'transaccion_pago_id');
    }
}
