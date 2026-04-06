<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuthLoginRateLimitMiddleware
 *
 * Rate limiting más estricto específicamente para /auth/login
 * Previene brute force attacks limitando intentos de login fallidos
 *
 * Configuración:
 * - AUTH_LOGIN_RATE_LIMIT: máximo de intentos (default 5)
 * - AUTH_LOGIN_RATE_WINDOW: ventana de tiempo en segundos (default 60)
 *
 * Retorna 429 Too Many Requests si se exceede el límite
 */
class AuthLoginRateLimitMiddleware
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $cacheKey = "auth_login_attempts:{$ip}";

        $attempts = (int) Cache::get($cacheKey, 0);
        $maxAttempts = (int) env('AUTH_LOGIN_RATE_LIMIT', self::MAX_ATTEMPTS);
        $windowSeconds = (int) env('AUTH_LOGIN_RATE_WINDOW', self::WINDOW_SECONDS);

        // Si se excedió el límite
        if ($attempts >= $maxAttempts) {
            return response()->json([
                'error'   => 'Too Many Login Attempts',
                'message' => "Demasiados intentos de login. Intenta de nuevo en {$windowSeconds} segundos.",
                'code'    => 429,
                'retry_after' => $windowSeconds,
            ], 429, [
                'X-RateLimit-Limit'     => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After'           => $windowSeconds,
            ]);
        }

        // Incrementar contador
        if ($attempts === 0) {
            Cache::put($cacheKey, 1, $windowSeconds);
        } else {
            Cache::increment($cacheKey);
        }

        $response = $next($request);

        // Agregar headers de rate limiting
        $remaining = max(0, $maxAttempts - ($attempts + 1));
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', $remaining);
        $response->headers->set('X-RateLimit-Reset', now()->addSeconds($windowSeconds)->timestamp);

        return $response;
    }
}
