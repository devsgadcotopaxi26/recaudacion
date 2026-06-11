<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: [
            '10.10.0.253',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Excluir webhook de CSRF
        $middleware->validateCsrfTokens(except: [
            'webhook/*',
            '/webhook/*',
        ]);

        // Middleware alias para API y Spatie Permission
        $middleware->alias([
            'api.token' => \App\Http\Middleware\ValidateApiToken::class,
            'redis.ratelimit' => \App\Http\Middleware\RedisRateLimiter::class,
            // Spatie Permission
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ─── [AÑADIDO] Manejador para el límite de peticiones (429) ───
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Límite de solicitudes excedido para este banco.',
                    'retry_after_seconds' => $e->getHeaders()['Retry-After'] ?? null,
                    'error_code' => 'API_RATE_LIMIT_EXCEEDED'
                ], 429);
            }
        });

        // Manejar sesión expirada con mensaje amigable
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, \Illuminate\Http\Request $request) {
            if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Su sesión ha expirado. Por favor, inicie sesión nuevamente.'], 401);
                }

                return redirect()->route('login')->with('warning', 'Su sesión ha expirado. Por favor, inicie sesión nuevamente.');
            }

            return $response;
        });
    })->create();
