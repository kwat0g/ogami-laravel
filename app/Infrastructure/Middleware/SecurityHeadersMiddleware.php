<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects OWASP-recommended security headers on every HTTP response.
 *
 * This middleware mirrors the headers set in docker/nginx/default.conf so that
 * the dev `php artisan serve` server is equally protected during development
 * and automated security scans.
 */
final class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Remove PHP's native X-Powered-By before it is sent by the SAPI (ZAP [10037])
        header_remove('X-Powered-By');
        header_remove('Server');

        /** @var Response $response */
        $response = $next($request);

        // Remove information-disclosure headers from Symfony response as well (ZAP [10037])
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Ensure nosniff on every response including static routes (ZAP [10021])
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        // Cross-Origin isolation headers (ZAP [90004])
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'unsafe-none');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

        // HSTS: enforce HTTPS on subsequent requests.
        // Only set when the connection is secure (behind HTTPS/Nginx) to avoid
        // locking out plain-HTTP dev environments.
        if ($request->secure() || $request->header('X-Forwarded-Proto') === 'https') {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Tightened CSP — explicit fallback directives resolve ZAP [10055]
        // object-src 'none' and base-uri 'self' remove common fallback omissions
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            ."script-src 'self'; "
            ."style-src 'self' 'unsafe-inline'; "
            ."img-src 'self' data: blob:; "
            ."font-src 'self'; "
            ."connect-src 'self' ws: wss:; "
            ."frame-ancestors 'self'; "
            ."form-action 'self'; "
            ."base-uri 'self'; "
            ."object-src 'none'; "
            ."media-src 'none'; "
            ."worker-src 'self'"
        );

        return $response;
    }
}
