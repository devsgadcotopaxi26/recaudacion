<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->onDelete('cascade');
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
