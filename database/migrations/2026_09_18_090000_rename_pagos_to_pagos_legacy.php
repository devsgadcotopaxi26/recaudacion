<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Reestructuración cabecera/detalle (transacciones_pago + pago_detalles).
 *
 * `pagos` pasa a `pagos_legacy`: se conserva intacta y de solo lectura,
 * como archivo histórico de todo lo registrado antes de este cambio
 * (bancario y pasarela ciudadana). Nada la vuelve a escribir después de
 * esta migración — el modelo App\Models\Pago queda apuntando aquí solo
 * para consultas históricas.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::rename('pagos', 'pagos_legacy');
    }

    public function down(): void
    {
        Schema::rename('pagos_legacy', 'pagos');
    }
};
