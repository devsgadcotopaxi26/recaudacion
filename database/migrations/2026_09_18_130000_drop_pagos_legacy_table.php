<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina `pagos_legacy` (la vieja `pagos`, renombrada durante la
 * reestructuración cabecera/detalle). Confirmado antes de este borrado:
 * de las 9 filas reales, 4 tienen certificado_token con GETs registrados
 * en nginx access.log, pero TODOS desde 172.19.0.1 (red interna del
 * contenedor/localhost) — ninguna visita externa real. Estamos en
 * desarrollo, sin certificados entregados a terceros: se acepta el
 * riesgo de perder ese histórico.
 *
 * `transacciones.pago_id` tenía una FK real hacia esta tabla
 * (`transacciones_pago_id_foreign`, heredada del CREATE TABLE original
 * apuntando a `pagos` antes del RENAME). Hay que liberarla primero o el
 * DROP TABLE falla. La columna `pago_id` en sí se deja intacta (fuera de
 * alcance de esta tarea): queda como bigint nullable sin FK, historial
 * muerto de filas de log previas a TransaccionPago, igual que ya se
 * documentó en la migración 2026_09_18_090300.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropForeign('transacciones_pago_id_foreign');
        });

        Schema::dropIfExists('pagos_legacy');
    }

    public function down(): void
    {
        // Irreversible: los datos de pagos_legacy no se pueden reconstruir.
        // Si se necesita revertir, restaurar desde un backup de BD.
        throw new \RuntimeException(
            'No reversible: pagos_legacy fue eliminada permanentemente. Restaurar desde backup si es necesario.'
        );
    }
};
