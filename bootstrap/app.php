<?php

use App\Infrastructure\Middleware\DepartmentScopeMiddleware;
use App\Infrastructure\Middleware\EnsureJsonApiMiddleware;
use App\Infrastructure\Middleware\SecurityHeadersMiddleware;
use App\Infrastructure\Middleware\SodMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ── Global middleware ─────────────────────────────────────────────────
        $middleware->append(SecurityHeadersMiddleware::class);

        // ── API group: force JSON response for every /api/* request ───────────
        // Ensures unauthenticated direct browser hits return a JSON 401 instead
        // of an HTML redirect, preventing route and auth-flow enumeration.
        $middleware->appendToGroup('api', EnsureJsonApiMiddleware::class);

        // ── Sanctum SPA (cookie) auth for the API group ───────────────────────
        // Enables session-based authentication for requests from known stateful
        // domains (SANCTUM_STATEFUL_DOMAINS). Bearer token auth still works.
        $middleware->statefulApi();

        // ── Named middleware aliases ──────────────────────────────────────────
        $middleware->alias([
            'sod' => SodMiddleware::class,
            'dept_scope' => DepartmentScopeMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);

        // ── Authentication redirect ───────────────────────────────────────────
        // Prevent redirect to 'login' route for API requests
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null; // Return null to trigger 401 response instead of redirect
            }

            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // All rendering is handled in App\Exceptions\Handler via render()
        $exceptions->renderable(function (\Throwable $e, $request) {
            /** @var \App\Exceptions\Handler $handler */
            $handler = app(\App\Exceptions\Handler::class);

            if ($request->expectsJson() || $request->is('api/*')) {
                return $handler->render($request, $e);
            }
        });
    })->create();
