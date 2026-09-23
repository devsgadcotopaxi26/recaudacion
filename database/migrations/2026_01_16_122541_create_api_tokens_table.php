<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONSOLIDADO (2026-09-23): integra directamente usuario/password_hash
 * (credenciales de login para el endpoint de autenticación), que vivían en
 * una migración add_credentials_to_api_tokens_table separada — esa
 * migración también agregaba access_token/token_expira_en, y
 * add_refresh_token_to_api_tokens_table agregaba refresh_token/
 * refresh_token_expira_en; esas 4 columnas nunca se usaron y ya se habían
 * eliminado (drop_dead_token_columns_from_api_tokens_table). Con nada que
 * agregar y nada que quitar, las 3 migraciones quedaron redundantes y se
 * eliminaron. Cambio de FORMA de cómo se llega al esquema, no de
 * sustancia — verificado con SHOW CREATE TABLE idéntico antes/después vía
 * migrate:fresh en una base de prueba aparte.
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('entidad_nombre'); // Nombre del banco/entidad

            // Credenciales de login para el endpoint de autenticación.
            $table->string('usuario')->nullable()->unique();
            $table->string('password_hash')->nullable(); // bcrypt hash

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
