<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadata de auditoría para la reversión de un pago bancario
 * (TransaccionPago::revertir()). Separada de `estado` (que ya soporta
 * el valor 'reversado' desde create_transacciones_pago_table) porque
 * 'reversado' por sí solo no dice quién la pidió, cuándo ni por qué —
 * requerido por la regla de negocio de no borrar nunca el registro
 * original y conservar un rastro de auditoría.
 *
 * Todo nullable y sin default: filas existentes quedan con los tres
 * campos en null (nunca revertidas), sin romper nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->timestamp('revertido_en')->nullable()->after('estado');
            $table->text('revertido_motivo')->nullable()->after('revertido_en');
            $table->unsignedBigInteger('revertido_por_api_token_id')->nullable()->after('revertido_motivo');

            $table->foreign('revertido_por_api_token_id')
                ->references('id')->on('api_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->dropForeign(['revertido_por_api_token_id']);
            $table->dropColumn(['revertido_en', 'revertido_motivo', 'revertido_por_api_token_id']);
        });
    }
};
