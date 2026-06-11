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
        'pago_id',
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
     * Relación con pago
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /**
     * Crear registro de transacción
     */
    public static function registrar(
        ?int $pagoId,
        string $tipo,
        ?array $request,
        ?array $response,
        ?string $estado = null,
        ?string $mensaje = null
    ): self {
        return self::create([
            'pago_id' => $pagoId,
            'tipo' => $tipo,
            'datos_request' => $request,
            'datos_response' => $response,
            'estado' => $estado,
            'mensaje' => $mensaje,
            'ip_origen' => request()->ip(),
        ]);
    }
}
