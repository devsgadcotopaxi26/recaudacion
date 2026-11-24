<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevenir ataques de clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevenir MIME-sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Activar protección XSS del navegador
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Content Security Policy
        // Solo en local con `npm run dev` activo (existe public/hot): permitir
        // el servidor de Vite (assets + websocket de HMR). En producción no
        // existe public/hot, así que la política queda igual.
        $vite = '';
        $viteWs = '';
        if (app()->environment('local') && Vite::isRunningHot()) {
            $vite = ' ' . rtrim(trim((string) @file_get_contents(public_path('hot'))), '/');
            $viteWs = ' ' . preg_replace('#^http#', 'ws', trim($vite));
        }

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.bunny.net{$vite}; " .
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net{$vite}; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' https://fonts.bunny.net{$vite}; " .
            "connect-src 'self'{$vite}{$viteWs};"
        );

        // HSTS (solo en producción con HTTPS)
        if ($request->secure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
