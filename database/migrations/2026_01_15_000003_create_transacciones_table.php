<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->onDelete('set null');
            $table->enum('tipo', ['api_call', 'webhook', 'callback'])->default('api_call');
            $table->json('datos_request')->nullable();
            $table->json('datos_response')->nullable();
            $table->string('estado', 50)->nullable();
            $table->text('mensaje')->nullable();
            $table->string('ip_origen', 45)->nullable();
            $table->timestamps();

            $table->index('pago_id');
            $table->index('tipo');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacciones');
    }
};
