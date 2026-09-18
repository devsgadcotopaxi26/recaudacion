<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hasta ahora, transacciones_pago distinguía banco vs. pasarela ciudadana
 * de forma implícita (api_token_id presente = banco; link_pago/
 * datos_facturacion presentes = pasarela) — fácil de malinterpretar en
 * reportes futuros o debug. Esta columna lo hace explícito, asignado en
 * el momento de creación por quien la originó (BancaController::
 * registrarPago() = 'banco'; PagoController::procesar() = 'pasarela_ciudadana').
 *
 * Backfill: TODAS las filas existentes al momento de esta migración se
 * marcan 'banco' — confirmado con evidencia (api_token_id presente y
 * link_pago/datos_facturacion vacíos en las 14 filas reales existentes)
 * que ninguna es de pasarela ciudadana real.
 *
 * Se agrega con DEFAULT 'banco' (así MySQL rellena las filas existentes
 * automáticamente sin necesidad de doctrine/dbal, que este proyecto no
 * tiene instalado) y luego se le quita el DEFAULT vía SQL directo, para
 * que el código SIEMPRE tenga que asignarlo explícitamente de ahora en
 * adelante — un olvido debe fallar ruidosamente (constraint), no caer
 * silenciosamente en 'banco'.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->enum('canal', ['banco', 'pasarela_ciudadana'])
                ->default('banco')
                ->after('placa');
        });

        DB::statement("ALTER TABLE transacciones_pago MODIFY canal ENUM('banco', 'pasarela_ciudadana') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->dropColumn('canal');
        });
    }
};
