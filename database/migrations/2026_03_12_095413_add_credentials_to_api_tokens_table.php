<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            // Credenciales de login para el endpoint de autenticación
            $table->string('usuario')->nullable()->unique()->after('entidad_nombre');
            $table->string('password_hash')->nullable()->after('usuario'); // bcrypt hash

            // Token dinámico de sesión (generado en cada login)
            $table->string('access_token', 80)->nullable()->unique()->after('token');
            $table->timestamp('token_expira_en')->nullable()->after('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn(['usuario', 'password_hash', 'access_token', 'token_expira_en']);
        });
    }
};
