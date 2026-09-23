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
 * apuntando a `pagos` antes del RENAME) — históricamente había que
 * liberarla primero o el DROP TABLE fallaba.
 *
 * CONSOLIDADO (2026-09-23): pago_id (y su FK) ya no existen en absoluto —
 * se quitaron directamente de create_transacciones_table (ver esa
 * migración) en vez de crearse y luego borrarse. El dropForeign() de abajo
 * ya no tiene nada que soltar, se quitó.
 */
return new class extends Migration {
    public function up(): void
    {
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
