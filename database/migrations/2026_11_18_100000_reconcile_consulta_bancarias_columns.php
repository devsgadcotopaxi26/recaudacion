<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliación de esquema para ambientes que ya existían ANTES de la
 * consolidación de migraciones (commit 2764fc8).
 *
 * Esa consolidación movió codigo_consulta, estado y expira_en dentro del
 * CREATE TABLE de 2026_03_12_155518_create_consulta_bancarias_table. En
 * ambientes donde esa migración ya figuraba como "Ran" (producción), el
 * CREATE nunca se vuelve a ejecutar, así que las columnas nunca se crean y
 * BancaController::consultarDeuda() falla silenciosamente al insertar.
 *
 * Idempotente y solo aditiva: en una BD nueva (migrate:fresh) es un no-op.
 * No elimina columnas legadas (entidad_nombre, monto_a_pagar, detalle_json);
 * son nullable / con default y no bloquean el insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        $faltaCodigo = !Schema::hasColumn('consulta_bancarias', 'codigo_consulta');
        $faltaEstado = !Schema::hasColumn('consulta_bancarias', 'estado');
        $faltaExpira = !Schema::hasColumn('consulta_bancarias', 'expira_en');

        if ($faltaCodigo || $faltaEstado || $faltaExpira) {
            Schema::table('consulta_bancarias', function (Blueprint $table) use ($faltaCodigo, $faltaEstado, $faltaExpira) {
                if ($faltaCodigo) {
                    $table->string('codigo_consulta', 30)
                        ->nullable()
                        ->after('id')
                        ->comment('Código único para trazar consulta con pago: CON-YYYYMMDD-XXXXX');
                }
                if ($faltaEstado) {
                    $table->enum('estado', ['pendiente', 'pagado', 'expirado'])
                        ->default('pendiente')
                        ->after('total_a_pagar')
                        ->comment('Estado de la consulta');
                }
                if ($faltaExpira) {
                    $table->timestamp('expira_en')
                        ->nullable()
                        ->after('estado')
                        ->comment('La consulta expira en 24 horas');
                }
            });
        }

        // UNIQUE aparte: las filas existentes quedan con NULL, que MySQL
        // permite repetir en un índice único.
        if (!Schema::hasIndex('consulta_bancarias', ['codigo_consulta'], 'unique')) {
            Schema::table('consulta_bancarias', function (Blueprint $table) {
                $table->unique('codigo_consulta');
            });
        }
    }

    /**
     * No-op a propósito: en una BD creada desde cero estas columnas
     * pertenecen al CREATE TABLE original, y revertirlas aquí lo rompería.
     */
    public function down(): void
    {
        //
    }
};
