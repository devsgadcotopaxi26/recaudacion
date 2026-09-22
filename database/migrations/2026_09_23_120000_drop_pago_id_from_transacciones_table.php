<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina transacciones.pago_id — columna huérfana confirmada por
 * auditoría. Reconstruido vía git log/git show sobre database/migrations/
 * (sin tocar el working directory): `pagos` sí existió desde el primer
 * commit del proyecto (2026-06-11, create_pagos_table.php), con una FK
 * real de esta columna hacia ella. `pagos` se renombró a `pagos_legacy` y
 * finalmente se eliminó (2026_09_18_130000_drop_pagos_legacy_table.php),
 * migración que ya soltó la FK `transacciones_pago_id_foreign` antes de
 * borrar la tabla — pago_id quedó como columna simple sin constraint
 * desde entonces, sin ningún belongsTo() en el modelo Transaccion.
 *
 * NO se toca transaccion_pago_id (la FK vigente hacia transacciones_pago,
 * activamente usada en todo el sistema vía Transaccion::transaccionPago())
 * — son dos columnas de épocas distintas del esquema, confirmado que el
 * código nunca las usa indistintamente (grep de ->pago_id como acceso
 * real a la columna: 0 resultados en toda la app).
 *
 * Nota aparte, NO implementada en esta migración: varias respuestas JSON
 * reutilizan el string 'pago_id' como nombre de clave para un concepto
 * completamente distinto (el id de TransaccionPago, no esta columna).
 * Una de ellas (BancaController::reporteConciliacion(), campo público
 * detalle_pagos[].pago_id) es parte del contrato documentado de la API
 * bancaria — renombrarla requiere coordinación con las entidades
 * integradas, fuera de alcance de este cambio de esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropColumn('pago_id');
        });
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            // Se restaura como columna simple, SIN la FK original: esa FK
            // apuntaba a `pagos`, tabla que ya no existe en ningún punto
            // posterior de esta cadena de migraciones (eliminada en
            // 2026_09_18_130000). Recrear la FK aquí rompería un
            // migrate:fresh completo.
            $table->unsignedBigInteger('pago_id')->nullable()->after('id');
            $table->index('pago_id');
        });
    }
};
