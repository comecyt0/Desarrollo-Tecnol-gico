<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class ApiAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            if (!$token = JWTAuth::getToken()) {
                throw new \Illuminate\Auth\AuthenticationException('Token not provided');
            }

            if (!$user = JWTAuth::authenticate($token)) {
                throw new \Illuminate\Auth\AuthenticationException('Invalid token');
            }

            auth()->setUser($user);
        } catch (\Exception $e) {
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated');
        }

        return $next($request);
    }
}
