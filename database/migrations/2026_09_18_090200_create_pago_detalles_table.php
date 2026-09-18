<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle: una fila por año fiscal cubierto dentro de una transacción de
 * pago. `placa` y `estado` se desnormalizan desde transacciones_pago a
 * propósito: conciliarPagosLocales() (el path más transitado del sistema,
 * corre en cada consulta de deuda) necesita filtrar por placa+estado sin
 * JOIN. Es seguro porque una transacción es atómica (todo o nada, vía
 * DB::transaction() en BancaController::registrarPago()) — todas sus
 * filas de detalle comparten siempre el mismo estado que su cabecera, no
 * hay UPDATE parcial posible que las desincronice.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pago_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaccion_pago_id')
                ->constrained('transacciones_pago')
                ->cascadeOnDelete();

            // Desnormalizados desde transacciones_pago (ver docblock).
            $table->string('placa', 10);
            $table->enum('estado', ['pendiente', 'pagado', 'fallido', 'expirado', 'reversado'])
                ->default('pendiente');

            $table->integer('anio_fiscal');
            $table->decimal('monto_impuesto', 10, 2)->default(0);
            $table->decimal('monto_mora', 10, 2)->default(0);
            $table->decimal('monto_total', 10, 2);

            $table->timestamps();

            // Un mismo año no puede repetirse dos veces dentro de la misma
            // transacción.
            $table->unique(['transaccion_pago_id', 'anio_fiscal']);

            // Consultas de "qué años están pagados" (conciliarPagosLocales).
            $table->index(['placa', 'anio_fiscal']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_detalles');
    }
};
