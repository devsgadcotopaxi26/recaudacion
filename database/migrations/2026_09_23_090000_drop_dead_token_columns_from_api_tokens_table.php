<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina access_token, refresh_token, token_expira_en y
 * refresh_token_expira_en de api_tokens — código muerto confirmado en
 * auditoría: ningún flujo real (login/refresh en AuthBancaController)
 * escribe jamás en estas 4 columnas, todas se migraron por completo a la
 * tabla relacional api_auth_tokens (más nueva, soporta multi-sesión, con
 * api_token_id FK real y ultimo_uso).
 *
 * `token` (el estático, usado como fallback de compatibilidad en
 * ValidateApiToken) NO se toca — sigue en uso real.
 *
 * ValidateApiToken.php:30,35,38 sí menciona `token_expira_en`, pero es
 * sobre el resultado de un JOIN que selecciona 'api_tokens.*' JUNTO CON
 * 'api_auth_tokens.token_expira_en' (mismo nombre de columna) — el
 * segundo sobreescribe al primero en el objeto resultante, así que el
 * valor que el código realmente lee siempre fue el de api_auth_tokens,
 * nunca el de api_tokens. La rama de fallback estático (sin JOIN) nunca
 * vuelve a tocar token_expira_en. Confirmado antes de esta migración,
 * no requiere ningún cambio de código en ese archivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'access_token',
                'refresh_token',
                'token_expira_en',
                'refresh_token_expira_en',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->string('access_token', 80)->nullable()->unique()->after('token');
            $table->timestamp('token_expira_en')->nullable()->after('access_token');
            $table->string('refresh_token', 80)->nullable()->unique()->after('token_expira_en');
            $table->timestamp('refresh_token_expira_en')->nullable()->after('refresh_token');
        });
    }
};
