<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia UNIQUE(referencia_externa) global a UNIQUE(api_token_id,
 * referencia_externa) compuesta.
 *
 * Antes, ningún banco podía usar un referencia_externa que OTRO banco ya
 * hubiera usado — restricción global sin sentido de negocio real (dos
 * entidades distintas pueden coincidir en su propio esquema de
 * numeración, ej. ambas emiten "TXN-000001"). Lo que sí debe seguir
 * bloqueado es que la MISMA entidad reutilice su propio referencia_externa
 * (protección de idempotencia real contra reintentos duplicados).
 *
 * Sin paso de limpieza de datos: la restricción anterior (más estricta)
 * ya garantiza que no puede existir ninguna fila que viole la nueva regla
 * (más permisiva) — confirmado con evidencia antes de escribir esta
 * migración (0 filas con referencia_externa repetido, y la tabla nunca
 * pudo tener una porque el UNIQUE global lo bloqueaba desde su creación).
 *
 * api_token_id es nullable (canal 'pasarela_ciudadana' no tiene entidad
 * bancaria) — MySQL trata cada NULL como distinto en un índice único, así
 * que varias transacciones de pasarela ciudadana con api_token_id NULL
 * pueden coexistir sin chocar entre sí, igual que hoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->dropUnique('transacciones_pago_referencia_externa_unique');
            $table->unique(['api_token_id', 'referencia_externa']);
        });
    }

    public function down(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            // El índice compuesto de arriba (api_token_id como columna
            // líder) es hoy el ÚNICO que cubre la FK de api_token_id hacia
            // api_tokens — MySQL rechaza dropUnique() sin esto (error 1553,
            // confirmado corriendo el rollback real). Se restaura primero
            // un índice simple sobre api_token_id para que la FK nunca se
            // quede sin cobertura, luego sí se puede soltar el compuesto.
            $table->index('api_token_id');
            $table->dropUnique(['api_token_id', 'referencia_externa']);
            $table->unique('referencia_externa');
        });
    }
};
