<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `transacciones` (log técnico de llamadas api_call/webhook/callback a la
 * pasarela de pago) sigue siendo un concepto DISTINTO de la nueva cabecera
 * `transacciones_pago` — no se fusionan. Esta tabla de log se queda, solo
 * gana una columna para apuntar a la cabecera nueva en vez de a `pagos`.
 *
 * `pago_id` (FK a pagos_legacy) se deja intacto para las filas de log ya
 * existentes, generadas antes de esta reestructuración. Las llamadas
 * nuevas de PaymentGatewayService usan `transaccion_pago_id`.
 *
 * CONSOLIDADO (2026-09-23): pago_id ya no existe (se quitó directamente de
 * create_transacciones_table, ver esa migración) — el ->after() de abajo
 * se ajustó de 'pago_id' a 'id' en consecuencia. Esta migración en sí NO
 * se pudo fusionar en el create: depende de que transacciones_pago ya
 * exista, y esa tabla se crea después en la cadena cronológica.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->foreignId('transaccion_pago_id')
                ->nullable()
                ->after('id')
                ->constrained('transacciones_pago')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropForeign(['transaccion_pago_id']);
            $table->dropColumn('transaccion_pago_id');
        });
    }
};
