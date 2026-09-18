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
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('transacciones_pago', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 10);

            // Identifica la transacción real del banco/pasarela. Ahora SÍ
            // tiene sentido como UNIQUE de una sola columna: una fila =
            // una operación de cobro real, no se repite por diseño (a
            // diferencia del viejo referencia_pago por año, que forzaba
            // duplicados en pagos multi-año).
            $table->string('referencia_externa', 100)->nullable()->unique();

            $table->string('codigo_consulta', 30)->nullable();
            $table->unsignedBigInteger('consulta_bancaria_id')->nullable();
            $table->unsignedBigInteger('api_token_id')->nullable();
            $table->string('entidad_recaudadora', 100)->nullable();

            $table->decimal('monto_total', 12, 2);
            $table->enum('estado', ['pendiente', 'pagado', 'fallido', 'expirado', 'reversado'])
                ->default('pendiente');
            $table->timestamp('fecha_pago')->nullable();

            $table->string('certificado_token', 64)->nullable()->unique();

            // Pasarela ciudadana (Pago Medios): nullable, sin impacto en
            // el flujo bancario.
            $table->text('link_pago')->nullable();
            $table->json('datos_facturacion')->nullable();

            // Snapshot del vehículo (marca/modelo/año/descripción) +
            // metodo_pago — misma forma que ya guardaba `pagos.datos_adicionales`.
            $table->json('datos_adicionales')->nullable();

            $table->timestamps();

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
