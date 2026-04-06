<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'revisor' => \App\Http\Middleware\RevisorMiddleware::class,
            'evaluador' => \App\Http\Middleware\EvaluadorMiddleware::class,
            'api.auth' => \App\Http\Middleware\ApiAuthenticate::class,
        ]);

        // Prevent API routes from redirecting to login
        $middleware->statefulApi();

        // Exclude API routes from CSRF verification
        // JWT-based APIs don't need CSRF protection
        $middleware->validateCsrfTokens(except: [
            '/api/*',
            '/sanctum/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON for API requests
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Custom exception rendering for API errors
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(
                    ['message' => 'Unauthenticated.', 'error' => 'Credenciales inválidas'],
                    401
                );
            }
        });

        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(
                    ['message' => 'Validation failed', 'errors' => $e->errors()],
                    422
                );
            }
        });
    })->create();
