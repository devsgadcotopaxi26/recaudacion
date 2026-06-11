<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class RedisRateLimiter
{
    /**
     * Rate limiter usando Redis con sliding window
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Verificar rate limiting
        if ($this->isRateLimited($ip)) {
            return response()->json([
                'error' => 'Demasiadas solicitudes. Por favor, espera un momento antes de intentar nuevamente.',
                'retry_after' => $this->getRetryAfter($ip)
            ], 429);
        }

        // Registrar la solicitud
        $this->recordRequest($ip);

        return $next($request);
    }

    /**
     * Verificar si la IP está limitada
     */
    private function isRateLimited(string $ip): bool
    {
        $now = time();

        // Límite por minuto: 10 requests
        $keyMinute = "ratelimit:minute:{$ip}";
        $requestsLastMinute = $this->countRequests($keyMinute, $now - 60);

        if ($requestsLastMinute >= 10) {
            return true;
        }

        // Límite por hora: 100 requests
        $keyHour = "ratelimit:hour:{$ip}";
        $requestsLastHour = $this->countRequests($keyHour, $now - 3600);

        if ($requestsLastHour >= 100) {
            return true;
        }

        // Verificar si está en blacklist temporal
        if (Redis::exists("ratelimit:blocked:{$ip}")) {
            return true;
        }

        return false;
    }

    /**
     * Contar requests en ventana de tiempo (sliding window)
     */
    private function countRequests(string $key, int $since): int
    {
        // Limpiar requests antiguos
        Redis::zremrangebyscore($key, 0, $since);

        // Contar requests desde el tiempo especificado
        return (int) Redis::zcard($key);
    }

    /**
     * Registrar request actual
     */
    private function recordRequest(string $ip): void
    {
        $now = time();

        // Registrar en ventana por minuto
        $keyMinute = "ratelimit:minute:{$ip}";
        Redis::zadd($keyMinute, $now, $now . ':' . uniqid());
        Redis::expire($keyMinute, 120); // 2 minutos de buffer

        // Registrar en ventana por hora
        $keyHour = "ratelimit:hour:{$ip}";
        Redis::zadd($keyHour, $now, $now . ':' . uniqid());
        Redis::expire($keyHour, 7200); // 2 horas de buffer

        // Si supera 50 requests por minuto, bloquear temporalmente
        $requestsLastMinute = $this->countRequests($keyMinute, $now - 60);
        if ($requestsLastMinute > 50) {
            Redis::setex("ratelimit:blocked:{$ip}", 300, 1); // Bloquear por 5 minutos
        }
    }

    /**
     * Obtener tiempo de espera en segundos
     */
    private function getRetryAfter(string $ip): int
    {
        if (Redis::exists("ratelimit:blocked:{$ip}")) {
            return (int) Redis::ttl("ratelimit:blocked:{$ip}");
        }

        return 60; // Default: esperar 1 minuto
    }
}
