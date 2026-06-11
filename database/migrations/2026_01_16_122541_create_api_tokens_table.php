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
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('entidad_nombre'); // Nombre del banco/entidad
            $table->string('token', 64)->unique(); // Token de autenticación
            $table->boolean('activo')->default(true);
            $table->integer('requests_permitidos')->default(1000); // Rate limit diario
            $table->timestamp('ultimo_uso')->nullable();
            $table->string('ip_permitida')->nullable(); // IP específica permitida
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('token');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
