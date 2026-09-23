<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONSOLIDADO (2026-09-23): integra directamente codigo_consulta,
 * api_token_id, estado y expira_en, que vivían en migraciones separadas
 * (add_api_token_id_to_consulta_bancarias_table, remove_user_id_from_
 * consulta_bancarias_table, y la parte de consulta_bancarias de
 * add_codigo_consulta_and_link_pagos — su parte sobre la tabla `pagos`
 * legacy se dejó intacta en su propio archivo, sin relación con este
 * consolidado). user_id se quita directamente (nunca tuvo reemplazo,
 * columna muerta desde el inicio real de uso del sistema) y
 * entidad_nombre/monto_a_pagar (agregadas y luego eliminadas en esta
 * misma sesión) ya ni se agregan. Cambio de FORMA de cómo se llega al
 * esquema, no de sustancia — verificado con SHOW CREATE TABLE idéntico
 * antes/después vía migrate:fresh en una base de prueba aparte.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('consulta_bancarias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_consulta', 30)
                ->unique()
                ->nullable()
                ->comment('Código único para trazar consulta con pago: CON-YYYYMMDD-XXXXX');
            $table->unsignedBigInteger('api_token_id')->nullable();
            $table->string('placa', 20)->index();
            $table->integer('anio_fiscal')->index();
            $table->enum('metodo_sri', ['deuda', 'historial'])->default('deuda');
            $table->decimal('valor_matricula', 12, 2)->default(0);
            $table->decimal('total_rodaje', 12, 2)->default(0);
            $table->decimal('total_mora', 12, 2)->default(0);
            $table->decimal('total_a_pagar', 12, 2)->default(0);
            $table->enum('estado', ['pendiente', 'pagado', 'expirado'])
                ->default('pendiente')
                ->comment('Estado de la consulta');
            $table->timestamp('expira_en')
                ->nullable()
                ->comment('La consulta expira en 24 horas');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Declarado como foreign() aparte (no inline en la columna) a
            // propósito: así el índice que respalda esta FK se crea
            // DESPUÉS de los de placa/anio_fiscal, igual orden que producía
            // la migración incremental original (api_token_id se agregaba
            // en una ALTER TABLE posterior a esos dos índices).
            $table->foreign('api_token_id')
                ->references('id')->on('api_tokens')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consulta_bancarias');
    }
};
