<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehiculo extends Model
{
    use HasFactory;

    protected $fillable = [
        'placa',
        'cedula_propietario',
        'propietario',
        'marca',
        'modelo',
        'anio',
        'avaluo',
        'valor_matricula',
        'tipo_vehiculo',
    ];

    protected $casts = [
        'avaluo' => 'decimal:2',
        'valor_matricula' => 'decimal:2',
        'anio' => 'integer',
    ];

    /**
     * Relación con pagos
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * Calcular el impuesto al rodaje según el valor de matrícula
     * 
     * Regla: 10% del valor de matrícula
     * - Mínimo: $10.00
     * - Máximo: $100.00
     */
    public function calcularImpuesto(): float
    {
        $valorMatricula = floatval($this->valor_matricula);

        // Calcular el 10% del valor de matrícula
        $impuesto = $valorMatricula * 0.10;

        // Aplicar mínimo de $10
        if ($impuesto < 10.00) {
            $impuesto = 10.00;
        }

        // Aplicar máximo de $100
        if ($impuesto > 100.00) {
            $impuesto = 100.00;
        }

        return round($impuesto, 2);
    }

    /**
     * Obtener el total a pagar (impuesto + otros cargos si aplica)
     */
    public function getTotalAPagar(): float
    {
        return $this->calcularImpuesto();
    }

    /**
     * Verificar si tiene pagos pendientes del año actual
     */
    public function tienePagoPendiente(int $anio = null): bool
    {
        $anio = $anio ?? date('Y');

        return $this->pagos()
            ->where('anio_fiscal', $anio)
            ->where('estado', 'pendiente')
            ->exists();
    }

    /**
     * Verificar si ya pagó el impuesto del año
     */
    public function yaPago(int $anio = null): bool
    {
        $anio = $anio ?? date('Y');

        return $this->pagos()
            ->where('anio_fiscal', $anio)
            ->where('estado', 'pagado')
            ->exists();
    }

    /**
     * Scope para buscar por placa y cédula
     */
    public function scopeBuscarPorPlacaCedula($query, string $placa, string $cedula)
    {
        return $query->where('placa', strtoupper($placa))
            ->where('cedula_propietario', $cedula);
    }
}
