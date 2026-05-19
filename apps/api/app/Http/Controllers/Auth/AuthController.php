<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! $token = auth()->guard('api')->attempt($credentials)) {
            Log::channel('security')->warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        Log::channel('security')->info('Successful login', [
            'user_id' => auth()->guard('api')->id(),
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        return $this->respondWithToken($token);
    }

    /**
     * @return JsonResponse
     */
    public function me()
    {
        $user = auth()->guard('api')->user();
        $user->loadMissing('rol', 'institucion');

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function logout()
    {
        auth()->guard('api')->logout();

        // Limpiar cookie HttpOnly del JWT
        $expiredCookie = cookie('comecyt_auth', '', -1, '/', null, false, true, false, 'Strict');

        return response()->json(['message' => 'Sesión cerrada exitosamente'])
            ->withCookie($expiredCookie);
    }

    /**
     * @return JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->guard('api')->refresh());
    }

    /**
     * @return JsonResponse
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth('api')->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'La contraseña actual no es correcta.'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    /**
     * @param  string  $token
     * @return JsonResponse
     */
    protected function respondWithToken($token)
    {
        $ttlSeconds = auth()->guard('api')->factory()->getTTL() * 60;
        $isSecure = app()->environment('production');

        // Cookie HttpOnly — invisible a JavaScript, protegida contra XSS
        $cookie = cookie(
            'comecyt_auth',    // nombre
            $token,            // valor
            $ttlSeconds / 60,  // minutos
            '/',               // path
            null,              // domain
            $isSecure,         // secure (solo HTTPS en producción)
            true,              // httpOnly
            false,             // raw
            'Strict'           // sameSite
        );

        $body = [
            'token_type' => 'bearer',
            'expires_in' => $ttlSeconds,
            'user' => auth()->guard('api')->user()->load('rol', 'institucion'),
        ];

        // Token en body solo en entorno local/dev — en producción solo viaja via cookie HttpOnly
        if (! app()->environment('production')) {
            $body['access_token'] = $token;
        }

        return response()->json($body)->withCookie($cookie);
    }
}
