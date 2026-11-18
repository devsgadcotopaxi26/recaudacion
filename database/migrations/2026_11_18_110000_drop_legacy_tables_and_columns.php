<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpieza de restos legados en ambientes previos a la consolidación de
 * migraciones (2764fc8 / fd5b27e), p. ej. producción.
 *
 * La historia original renombraba `pagos` a `pagos_legacy`, migraba sus
 * datos a transacciones_pago/pago_detalles y luego eliminaba tablas y
 * columnas muertas. Al consolidar, esos pasos desaparecieron, así que los
 * ambientes que ya existían conservan:
 *   - tablas `pagos` y `vehiculos` (sin uso: modelos eliminados en cd65995)
 *   - transacciones.pago_id (FK a pagos)
 *   - api_tokens.access_token / token_expira_en / refresh_token /
 *     refresh_token_expira_en (reemplazadas por api_auth_tokens)
 *   - consulta_bancarias.entidad_nombre (se obtiene vía api_token_id)
 *
 * Idempotente: en una BD nueva (migrate:fresh) es un no-op.
 * Guardia: si `pagos` tiene filas, aborta sin tocar nada — esos pagos
 * tendrían que migrarse antes a transacciones_pago/pago_detalles.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagos') && DB::table('pagos')->exists()) {
            throw new RuntimeException(
                'La tabla `pagos` tiene registros. Migrarlos a transacciones_pago/pago_detalles '
                . 'antes de eliminarla. No se modificó nada.'
            );
        }

        // 1) transacciones.pago_id → FK a pagos; quitarla antes de borrar pagos.
        if (Schema::hasColumn('transacciones', 'pago_id')) {
            $this->dropForeignKeysOn('transacciones', 'pago_id');
            Schema::table('transacciones', function (Blueprint $table) {
                $table->dropColumn('pago_id');
            });
        }

        // 2) Tablas legadas. pagos primero: tiene FK vehiculo_id → vehiculos.
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('vehiculos');

        // 3) Columnas muertas.
        $this->dropColumnsIfExist('api_tokens', [
            'access_token', 'token_expira_en', 'refresh_token', 'refresh_token_expira_en',
        ]);
        $this->dropColumnsIfExist('consulta_bancarias', ['entidad_nombre']);
    }

    /**
     * No-op a propósito: en una BD creada desde cero nada de esto existe, y
     * los datos legados no se pueden reconstruir. Restaurar desde respaldo.
     */
    public function down(): void
    {
        //
    }

    private function dropForeignKeysOn(string $tabla, string $columna): void
    {
        foreach (Schema::getForeignKeys($tabla) as $fk) {
            if ($fk['columns'] === [$columna]) {
                Schema::table($tabla, function (Blueprint $table) use ($fk) {
                    $table->dropForeign($fk['name']);
                });
            }
        }
    }

    private function dropColumnsIfExist(string $tabla, array $columnas): void
    {
        $existentes = array_values(array_filter(
            $columnas,
            fn ($c) => Schema::hasColumn($tabla, $c)
        ));

        if ($existentes) {
            // En MySQL, eliminar la columna elimina también sus índices (UNIQUE incluidos).
            Schema::table($tabla, function (Blueprint $table) use ($existentes) {
                $table->dropColumn($existentes);
            });
        }
    }
};
