<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token de autenticación requerido. Use: Authorization: Bearer {token}',
                'hint'    => 'Obtén tu token en POST /api/v1/auth/login',
            ], 401);
        }

        // 1) Buscar por access_token dinámico (generado en login) en la tabla relacional
        $apiToken = DB::table('api_auth_tokens')
            ->join('api_tokens', 'api_tokens.id', '=', 'api_auth_tokens.api_token_id')
            ->where('api_auth_tokens.access_token', $token)
            ->where('api_tokens.activo', true)
            ->select('api_tokens.*', 'api_auth_tokens.token_expira_en', 'api_auth_tokens.id as auth_id')
            ->first();

        if ($apiToken) {
            // Verificar expiración
            if ($apiToken->token_expira_en && now()->gt($apiToken->token_expira_en)) {
                Log::warning('API: Token expirado', [
                    'entidad'    => $apiToken->entidad_nombre,
                    'expiró_en' => $apiToken->token_expira_en,
                ]);

                // Limpiar token expirado
                DB::table('api_auth_tokens')
                    ->where('id', $apiToken->auth_id)
                    ->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Token expirado. Vuelve a hacer login en POST /api/v1/auth/login',
                ], 401);
            }
        } else {
            // 2) Fallback: buscar por token estático (compatibilidad hacia atrás)
            $apiToken = DB::table('api_tokens')
                ->where('token', $token)
                ->where('activo', true)
                ->first();
        }

        if (!$apiToken) {
            Log::warning('API: Token inválido o inactivo', [
                'token' => substr($token, 0, 10) . '...',
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token inválido o inactivo',
            ], 401);
        }

        // Verificar IP si está configurada
        if ($apiToken->ip_permitida && $apiToken->ip_permitida !== $request->ip()) {
            Log::warning('API: IP no autorizada', [
                'entidad'      => $apiToken->entidad_nombre,
                'ip_solicitante' => $request->ip(),
                'ip_permitida' => $apiToken->ip_permitida,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'IP no autorizada para este token',
            ], 403);
        }

        // Actualizar último uso
        if (isset($apiToken->auth_id)) {
            DB::table('api_auth_tokens')
                ->where('id', $apiToken->auth_id)
                ->update(['ultimo_uso' => now(), 'updated_at' => now()]);
        }
        
        DB::table('api_tokens')
            ->where('id', $apiToken->id)
            ->update(['ultimo_uso' => now()]);

        // Agregar información al request para uso en controladores
        $request->merge([
            'api_token_id'  => $apiToken->id,
            'entidad_nombre' => $apiToken->entidad_nombre,
        ]);

        return $next($request);
    }
}
