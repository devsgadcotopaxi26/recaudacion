<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            // Sin ->constrained('vehiculos'): la tabla `vehiculos` fue
            // eliminada (huérfana desde el primer commit, nunca usada por
            // ningún flujo real — ver 2026_09_21_115334_drop_vehiculos_table).
            // `pagos` en sí es histórica: se renombra a `pagos_legacy` y se
            // elimina por completo más adelante en esta misma cadena de
            // migraciones (2026_09_18_090000 y 2026_09_18_130000) — se deja
            // la columna sin FK únicamente para no romper el orden de
            // replay de `migrate:fresh`, no porque siga en uso.
            $table->foreignId('vehiculo_id');
            $table->decimal('monto_impuesto', 10, 2);
            $table->decimal('monto_total', 10, 2);
            $table->enum('estado', ['pendiente', 'pagado', 'fallido', 'expirado'])->default('pendiente');
            $table->string('referencia_pago')->nullable()->unique();
            $table->text('link_pago')->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->integer('anio_fiscal')->default(date('Y'));
            $table->json('datos_adicionales')->nullable();
            $table->timestamps();

            $table->index('referencia_pago');
            $table->index('estado');
            $table->index(['vehiculo_id', 'anio_fiscal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
