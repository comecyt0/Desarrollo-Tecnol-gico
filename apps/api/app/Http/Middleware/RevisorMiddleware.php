<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RevisorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!auth('api')->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = auth('api')->user();

        // Check if user has revisor role (rol_id = 2)
        if ($user->rol_id !== 2) {
            return response()->json([
                'message' => 'Unauthorized - Revisor access required',
                'error' => 'insufficient_permissions'
            ], 403);
        }

        return $next($request);
    }
}
