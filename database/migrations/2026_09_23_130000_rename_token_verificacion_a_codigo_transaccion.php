<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renombra transacciones_pago.token_verificacion a codigo_transaccion —
 * mismo dato, mismo propósito (identificador de bajo privilegio para
 * GET /verificar/{...}), solo el nombre y, en una migración aparte, el
 * formato de generación cambian. Ningún banco ni flujo real lo consume
 * todavía (confirmado en auditoría anterior), así que es un rename
 * seguro sin necesidad de mantener el nombre viejo en paralelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->renameColumn('token_verificacion', 'codigo_transaccion');
        });

        // renameColumn() no renombra el índice UNIQUE que cubre la
        // columna (queda con el nombre viejo apuntando a la columna
        // nueva) — se renombra aparte para que el esquema no quede con
        // un nombre de índice mentiroso.
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->renameIndex(
                'transacciones_pago_token_verificacion_unique',
                'transacciones_pago_codigo_transaccion_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->renameIndex(
                'transacciones_pago_codigo_transaccion_unique',
                'transacciones_pago_token_verificacion_unique'
            );
        });

        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->renameColumn('codigo_transaccion', 'token_verificacion');
        });
    }
};
