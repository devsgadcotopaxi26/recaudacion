<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina `entidad_recaudadora` (texto libre) de `transacciones_pago`: es
 * redundante con la FK real `api_token_id -> api_tokens.id`, que ya
 * identifica la entidad sin ambigüedad. Confirmado con datos reales que el
 * texto libre podía divergir del token que realmente creó la transacción
 * (7 de 9 filas de prueba tenían un nombre distinto al de su token).
 *
 * Confirmado por el usuario: cada entidad tiene siempre exactamente un
 * token por ambiente (producción y pruebas nunca conviven en la misma
 * base de datos) — no hace falta una tabla intermedia ni resolver el caso
 * de una entidad con varios tokens, `api_tokens` ya es la tabla de
 * entidades.
 *
 * Sin reemplazo de dato: el nombre de la entidad se lee desde ahora
 * siempre vía TransaccionPago::apiToken->entidad_nombre (o
 * TransaccionPago::nombreEntidad(), que además resuelve el caso
 * canal='pasarela_ciudadana' con api_token_id NULL).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->dropColumn('entidad_recaudadora');
        });
    }

    public function down(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->string('entidad_recaudadora', 100)->nullable()->after('placa');
        });
    }
};
