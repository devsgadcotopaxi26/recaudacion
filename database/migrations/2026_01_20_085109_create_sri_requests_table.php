<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sri_requests', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 10);
            $table->string('endpoint', 50); // verificacion, detalle
            $table->integer('status_code')->nullable();
            $table->integer('duration_ms');
            $table->boolean('success')->default(false);
            $table->string('error_type', 50)->nullable(); // timeout, network, 404, 500
            $table->text('error_message')->nullable();
            $table->boolean('cached')->default(false);
            $table->timestamps();

            // Índices para mejorar performance de queries
            $table->index('created_at');
            $table->index('success');
            $table->index('endpoint');
            $table->index(['placa', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sri_requests');
    }
};
