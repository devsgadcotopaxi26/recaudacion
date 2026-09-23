<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONSOLIDADO (2026-09-23): la parte de esta migración que tocaba
 * consulta_bancarias (codigo_consulta, estado, expira_en — vivas; y
 * monto_a_pagar, eliminada en esta misma sesión por duplicar
 * total_a_pagar) se integró directamente en create_consulta_bancarias_table
 * y se quitó de aquí. La parte que vincula `pagos` (tabla legacy, ya
 * eliminada más adelante en la cadena de migraciones, fuera del alcance de
 * este consolidado) se deja exactamente igual.
 */
return new class extends Migration {
    public function up(): void
    {
        // Vincular pago con consulta
        Schema::table('pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('consulta_bancaria_id')
                ->nullable()
                ->after('api_token_id')
                ->comment('Consulta que originó este pago');

            $table->foreign('consulta_bancaria_id')
                ->references('id')
                ->on('consulta_bancarias')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign(['consulta_bancaria_id']);
            $table->dropColumn('consulta_bancaria_id');
        });
    }
};
