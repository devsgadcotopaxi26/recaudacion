<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONSOLIDADO (2026-09-23): pago_id (FK a la tabla `pagos`, ya eliminada
 * hace tiempo — ver drop_pagos_legacy_table más adelante en la cadena) se
 * quita directamente de aquí en vez de crearse y luego borrarse
 * (drop_pago_id_from_transacciones_table, eliminada). transaccion_pago_id
 * (la FK vigente) NO se puede fusionar aquí: depende de la tabla
 * transacciones_pago, que se crea después en la cadena cronológica — sigue
 * en su propia migración (add_transaccion_pago_id_to_transacciones_table),
 * solo se ajustó su ->after('pago_id') a ->after('id') porque pago_id ya
 * no existe en este punto.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['api_call', 'webhook', 'callback'])->default('api_call');
            $table->json('datos_request')->nullable();
            $table->json('datos_response')->nullable();
            $table->string('estado', 50)->nullable();
            $table->text('mensaje')->nullable();
            $table->string('ip_origen', 45)->nullable();
            $table->timestamps();

            $table->index('tipo');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacciones');
    }
};
