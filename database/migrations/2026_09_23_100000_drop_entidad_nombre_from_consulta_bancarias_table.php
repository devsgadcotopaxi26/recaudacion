<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina consulta_bancarias.entidad_nombre — auditoría confirmó que se
 * escribe una sola vez al crear la consulta (BancaController.php:66) y
 * nunca se lee de vuelta en ningún reporte, servicio ni pantalla (grep
 * exhaustivo de ConsultaBancaria::/->entidad_nombre en toda la app, solo
 * escritura). No es un snapshot histórico funcional — es denormalización
 * sin uso real, mismo patrón que ya se corrigió antes en
 * transacciones_pago.entidad_recaudadora (con evidencia real de
 * divergencia entre el texto libre y el token que realmente creó la
 * fila). El nombre de la entidad se sigue leyendo, como en todo el resto
 * del sistema, vía api_tokens.entidad_nombre (FK api_token_id, ya
 * presente en esta misma tabla desde la migración que originalmente
 * agregó esta columna).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consulta_bancarias', function (Blueprint $table) {
            $table->dropColumn('entidad_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('consulta_bancarias', function (Blueprint $table) {
            $table->string('entidad_nombre')->nullable()->after('api_token_id');
        });
    }
};
