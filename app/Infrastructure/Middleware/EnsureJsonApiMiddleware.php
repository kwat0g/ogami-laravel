<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces every /api/* request to be treated as a JSON API call.
 *
 * Why this matters:
 *   - Without this, a browser navigating directly to /api/v1/users gets an
 *     HTML "login" redirect (status 302), which reveals the route exists and
 *     leaks the app's authentication flow.
 *   - With this applied, any unauthenticated direct hit returns a clean JSON
 *     401 {"success":false,"message":"Unauthenticated.","error_code":"UNAUTHENTICATED"}
 *     regardless of the browser's Accept header — no HTML, no redirects, no info leak.
 *   - Authenticated requests are unaffected; the header is already set by the
 *     frontend axios client (Accept: application/json).
 *
 * Registration: applied to the 'api' middleware group in bootstrap/app.php.
 */
final class EnsureJsonApiMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Override the client's Accept header so Laravel always resolves this
        // request as wanting JSON — wantJson() / expectsJson() return true.
        if (! $request->headers->has('Accept') || $request->header('Accept') === '*/*') {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
