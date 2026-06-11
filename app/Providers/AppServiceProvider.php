<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter; // [AÑADIDO]
use Illuminate\Cache\RateLimiting\Limit;     // [AÑADIDO]
use Illuminate\Http\Request;                // [AÑADIDO]
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ─── Definición de Rate Limiter para Bancos ─────────────
        RateLimiter::for('bancos', function (Request $request) {
            // 1. Intentar obtener el ID inyectado por el middleware
            $apiTokenId = $request->input('api_token_id');

            // 2. Si es nulo (problema de orden de middleware), usar el token mismo como identificador
            $tokenIdentificador = $apiTokenId ? 'id:' . $apiTokenId : 'bearer:' . md5($request->bearerToken() ?? '');

            if ($request->bearerToken()) {
                // Límite alto para cualquier petición que traiga un token (luego el middleware validará si es correcto)
                return Limit::perMinute(60)->by($tokenIdentificador);
            }

            // Fallback: Límite bajo por IP si no hay token en absoluto
            return Limit::perMinute(10)->by('ip:' . $request->ip());
        });
        // ────────────────────────────────────────────────────────
        // Compartir datos del usuario con Inertia (incluyendo roles)
        Inertia::share([
            'auth' => function () {
                $user = Auth::user();

                if (!$user) {
                    return ['user' => null];
                }

                // Construir objeto de usuario manualmente para evitar seriali zación de relaciones
                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        // Solo nombres de roles como array de strings
                        'roles' => $user->getRoleNames()->values()->all(),
                    ],
                ];
            },
        ]);

        // Solo forzar HTTPS cuando:
        // 1. APP_URL es HTTPS Y no es localhost
        // 2. O cuando viene de ngrok (x-forwarded-proto)

        $appUrl = config('app.url');
        $isLocalhost = str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1');

        // Forzar HTTPS si APP_URL es HTTPS pero NO es localhost
        if ($appUrl && str_starts_with($appUrl, 'https://') && !$isLocalhost) {
            URL::forceScheme('https');
        }

        // También forzar cuando viene de ngrok (proxy HTTPS)
        if (request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
