<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaccion extends Model
{
    use HasFactory;

    protected $table = 'transacciones';

    protected $fillable = [
        'transaccion_pago_id',
        'tipo',
        'datos_request',
        'datos_response',
        'estado',
        'mensaje',
        'ip_origen',
    ];

    protected $casts = [
        'datos_request' => 'array',
        'datos_response' => 'array',
    ];

    /**
     * Relación vigente — filas de log nuevas, ligadas a TransaccionPago.
     */
    public function transaccionPago(): BelongsTo
    {
        return $this->belongsTo(TransaccionPago::class);
    }

    /**
     * Crear registro de transacción (log de una llamada api_call/webhook/
     * callback a la pasarela), ligado a la cabecera TransaccionPago.
     */
    public static function registrar(
        ?int $transaccionPagoId,
        string $tipo,
        ?array $request,
        ?array $response,
        ?string $estado = null,
        ?string $mensaje = null
    ): self {
        return self::create([
            'transaccion_pago_id' => $transaccionPagoId,
            'tipo' => $tipo,
            'datos_request' => $request,
            'datos_response' => $response,
            'estado' => $estado,
            'mensaje' => $mensaje,
            'ip_origen' => request()->ip(),
        ]);
    }
}
