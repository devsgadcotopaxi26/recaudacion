<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthBancaController extends Controller
{
    /**
     * Body: { "entidad_nombre": "...", "usuario": "...", "password": "..." }
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entidad_nombre' => 'required|string|max:255',
            'usuario'        => 'required|string|unique:api_tokens,usuario|max:64',
            'password'       => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de registro inválidos',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // Crear el registro de la entidad
            $id = DB::table('api_tokens')->insertGetId([
                'entidad_nombre'      => $request->entidad_nombre,
                'usuario'             => $request->usuario,
                'password_hash'       => Hash::make($request->password),
                'token'               => Str::random(60), // Token estático de respaldo
                'activo'              => true,
                'requests_permitidos' => 1000,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            Log::info('API Auth: Nueva entidad registrada', [
                'entidad' => $request->entidad_nombre,
                'usuario' => $request->usuario,
                'ip'      => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entidad registrada exitosamente',
                'data'    => [
                    'id'             => $id,
                    'entidad_nombre' => $request->entidad_nombre,
                    'usuario'        => $request->usuario,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('API Auth: Error al registrar entidad', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al registrar la entidad',
            ], 500);
        }
    }

    /**
     * Login con usuario y contraseña → retorna access_token.

     *
     * POST /api/v1/auth/login
     * Body: { "usuario": "...", "password": "..." }
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'usuario'  => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario y contraseña son requeridos',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Buscar la entidad por usuario y que esté activa
        $apiToken = DB::table('api_tokens')
            ->where('usuario', $request->usuario)
            ->where('activo', true)
            ->first();

        if (!$apiToken || !Hash::check($request->password, $apiToken->password_hash)) {
            Log::warning('API Auth: Login fallido', [
                'usuario' => $request->usuario,
                'ip'      => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas',
            ], 401);
        }

        // Generar nuevo access_token (24h) y refresh_token (30 días)
        $newToken      = Str::random(60);
        $expiresAt     = now()->addHours(24);
        $refreshToken  = Str::random(64);
        $refreshExpires = now()->addDays(30);

        // Guardar el nuevo token en la tabla relacional
        DB::table('api_auth_tokens')->insert([
            'api_token_id'             => $apiToken->id,
            'access_token'             => $newToken,
            'refresh_token'            => $refreshToken,
            'token_expira_en'          => $expiresAt,
            'refresh_token_expira_en'  => $refreshExpires,
            'ultimo_uso'               => now(),
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        Log::info('API Auth: Login exitoso', [
            'entidad' => $apiToken->entidad_nombre,
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'success'       => true,
            'message'       => 'Autenticación exitosa',
            'access_token'  => $newToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => 86400, // 24h
            'expires_at'    => $expiresAt->toDateTimeString(),
            'entidad'       => $apiToken->entidad_nombre,
        ], 200);
    }

    /**
     * Refresca el access_token usando el refresh_token.
     *
     * POST /api/v1/auth/refresh
     * Body: { "refresh_token": "..." }
     */
    public function refresh(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'El refresh_token es requerido',
            ], 422);
        }

        $authRecord = DB::table('api_auth_tokens')
            ->join('api_tokens', 'api_tokens.id', '=', 'api_auth_tokens.api_token_id')
            ->where('api_auth_tokens.refresh_token', $request->refresh_token)
            ->where('api_tokens.activo', true)
            ->select('api_auth_tokens.*')
            ->first();

        if (!$authRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token inválido o inexistente',
            ], 401);
        }

        // Verificar expiración del refresh token
        if ($authRecord->refresh_token_expira_en && now()->gt($authRecord->refresh_token_expira_en)) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token expirado. Por favor, inicie sesión nuevamente.',
            ], 401);
        }

        // Generar nuevo access_token
        $newAccessToken = Str::random(60);
        $expiresAt      = now()->addHours(24);

        DB::table('api_auth_tokens')
            ->where('id', $authRecord->id)
            ->update([
                'access_token'    => $newAccessToken,
                'token_expira_en' => $expiresAt,
                'ultimo_uso'      => now(),
                'updated_at'      => now(),
            ]);

        return response()->json([
            'success'      => true,
            'access_token' => $newAccessToken,
            'token_type'   => 'Bearer',
            'expires_in'   => 86400,
            'expires_at'   => $expiresAt->toDateTimeString(),
        ], 200);
    }

    /**
     * Cierra sesión invalidando el access_token actual.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request)
    {
        $token = $request->bearerToken();

        if ($token) {
            DB::table('api_auth_tokens')
                ->where('access_token', $token)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
        ], 200);
    }
}
