<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de una operación real de cobro (estilo factura), sin importar
 * cuántos años fiscales cubra ni por qué canal entró.
 *
 * Soporta los dos ciclos de vida que hoy vivían en `pagos`:
 *  - Bancario (BancaController::registrarPago): nace 'pagado' directo.
 *  - Pasarela ciudadana (PagoController + PaymentGatewayService): nace
 *    'pendiente', pasa a 'pagado'/'fallido'/'reversado' vía webhook.
 *
 * `vehiculo_id` no se trae de `pagos`: se confirmó que está en null en
 * el 100% de los flujos vivos (bancario nunca lo asigna, pasarela lo
 * asigna explícitamente en null) — no aporta nada al modelo nuevo.
 *
 * CONSOLIDADO (2026-09-23): esta migración integra directamente el estado
 * final de varias migraciones incrementales que quedaron redundantes —
 * add_canal_to_transacciones_pago_table, drop_entidad_recaudadora_from_
 * transacciones_pago_table, add_token_verificacion_to_transacciones_pago_
 * table, make_referencia_externa_unique_per_entidad, y rename_token_
 * verificacion_a_codigo_transaccion (todas eliminadas, ninguna corrió en
 * ningún ambiente fuera de este). Cambio de FORMA de cómo se llega al
 * esquema, no de sustancia — verificado con SHOW CREATE TABLE idéntico
 * antes/después vía migrate:fresh en una base de prueba aparte.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('transacciones_pago', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 10);

            // Distingue banco vs. pasarela ciudadana explícitamente, asignado
            // en el momento de creación por quien la originó
            // (BancaController::registrarPago() = 'banco';
            // PagoController::procesar() = 'pasarela_ciudadana').
            $table->enum('canal', ['banco', 'pasarela_ciudadana']);

            // Identifica la transacción real del banco/pasarela. UNIQUE
            // compuesta con api_token_id (no global): dos entidades
            // distintas pueden coincidir en su propio esquema de numeración
            // (ej. ambas emiten "TXN-000001"), pero la MISMA entidad no
            // puede reutilizar su propio referencia_externa (idempotencia
            // real contra reintentos duplicados). api_token_id es nullable
            // (canal 'pasarela_ciudadana' no tiene entidad bancaria) — MySQL
            // trata cada NULL como distinto en un índice único.
            $table->string('referencia_externa', 100)->nullable();

            $table->string('codigo_consulta', 30)->nullable();
            $table->unsignedBigInteger('consulta_bancaria_id')->nullable();
            $table->unsignedBigInteger('api_token_id')->nullable();

            $table->decimal('monto_total', 12, 2);
            $table->enum('estado', ['pendiente', 'pagado', 'fallido', 'expirado', 'reversado'])
                ->default('pendiente');
            $table->timestamp('fecha_pago')->nullable();

            $table->string('certificado_token', 64)->nullable()->unique();

            // Identificador de verificación de bajo privilegio, separado de
            // certificado_token (acceso al certificado completo, incluye
            // PII) y de comprobante() (secuencial, nunca debe usarse como
            // credencial). Uso: GET /verificar/{codigo_transaccion} —
            // respuesta mínima, sin PII. Formato TRX-XXXXXX (alfabeto sin
            // caracteres ambiguos), pensado para tipearse a mano.
            $table->string('codigo_transaccion', 32)->nullable()->unique();

            // Pasarela ciudadana (Pago Medios): nullable, sin impacto en
            // el flujo bancario.
            $table->text('link_pago')->nullable();
            $table->json('datos_facturacion')->nullable();

            // Snapshot del vehículo (marca/modelo/año/descripción) +
            // metodo_pago — misma forma que ya guardaba `pagos.datos_adicionales`.
            $table->json('datos_adicionales')->nullable();

            $table->timestamps();

            $table->unique(['api_token_id', 'referencia_externa']);

            $table->foreign('consulta_bancaria_id')
                ->references('id')->on('consulta_bancarias')
                ->nullOnDelete();
            $table->foreign('api_token_id')
                ->references('id')->on('api_tokens')
                ->nullOnDelete();

            $table->index('placa');
            $table->index('estado');
            $table->index('codigo_consulta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacciones_pago');
    }
};
