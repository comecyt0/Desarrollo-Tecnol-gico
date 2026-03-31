<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * CircuitBreakerMiddleware
 *
 * Implementa el patrón Circuit Breaker a nivel de API.
 * Estados: CLOSED → OPEN → HALF-OPEN → CLOSED
 *
 * - CLOSED: funcionamiento normal
 * - OPEN: tras 5 errores 5xx consecutivos — rechaza todas las peticiones por 30s
 * - HALF-OPEN: permite una petición de prueba tras el timeout
 */
class CircuitBreakerMiddleware
{
    private const STATE_CLOSED    = 'CLOSED';
    private const STATE_OPEN      = 'OPEN';
    private const STATE_HALF_OPEN = 'HALF_OPEN';

    private const CACHE_KEY_STATE    = 'cb_state';
    private const CACHE_KEY_FAILURES = 'cb_failures';
    private const CACHE_KEY_OPENED   = 'cb_opened_at';

    public function handle(Request $request, Closure $next): Response
    {
        $threshold = (int) env('CIRCUIT_BREAKER_THRESHOLD', 5);
        $timeout   = (int) env('CIRCUIT_BREAKER_TIMEOUT', 30);

        $state    = Cache::get(self::CACHE_KEY_STATE, self::STATE_CLOSED);
        $failures = (int) Cache::get(self::CACHE_KEY_FAILURES, 0);
        $openedAt = Cache::get(self::CACHE_KEY_OPENED);

        // Si está OPEN, verificar si pasó el timeout para pasar a HALF-OPEN
        if ($state === self::STATE_OPEN) {
            $elapsed = now()->timestamp - (int) $openedAt;

            if ($elapsed >= $timeout) {
                Cache::put(self::CACHE_KEY_STATE, self::STATE_HALF_OPEN, 3600);
                $state = self::STATE_HALF_OPEN;
            } else {
                return response()->json([
                    'error'   => 'Circuit Breaker Open',
                    'message' => 'El sistema está temporalmente en mantenimiento. Intenta en unos momentos.',
                    'code'    => 503,
                    'retry_after' => $timeout - $elapsed,
                ], 503);
            }
        }

        $response = $next($request);
        $statusCode = $response->getStatusCode();

        // Actualizar estado del circuit breaker según la respuesta
        if ($statusCode >= 500) {
            $newFailures = $failures + 1;
            Cache::put(self::CACHE_KEY_FAILURES, $newFailures, 3600);

            if ($newFailures >= $threshold || $state === self::STATE_HALF_OPEN) {
                // Abrir el circuito
                Cache::put(self::CACHE_KEY_STATE, self::STATE_OPEN, 3600);
                Cache::put(self::CACHE_KEY_OPENED, now()->timestamp, 3600);

                \Log::error('CIRCUIT BREAKER ABIERTO', [
                    'failures'  => $newFailures,
                    'threshold' => $threshold,
                    'state'     => self::STATE_OPEN,
                ]);
            }
        } else {
            // Respuesta exitosa → resetear contadores (CLOSED / HALF-OPEN → CLOSED)
            if ($state === self::STATE_HALF_OPEN) {
                Cache::forget(self::CACHE_KEY_STATE);
                Cache::forget(self::CACHE_KEY_FAILURES);
                Cache::forget(self::CACHE_KEY_OPENED);
            } elseif ($failures > 0) {
                Cache::put(self::CACHE_KEY_FAILURES, 0, 3600);
            }
        }

        return $response;
    }
}
