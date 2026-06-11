<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.bunny.net; " .
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' https://fonts.bunny.net; " .
            "connect-src 'self';"
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
