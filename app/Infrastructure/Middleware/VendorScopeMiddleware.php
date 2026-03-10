<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VendorScopeMiddleware — ensures the authenticated user is a vendor user.
 *
 * Checks that:
 *   1. The user has the `vendor` role.
 *   2. The user has a `vendor_id` set (linked to a vendor record).
 *
 * Binds `vendor_scope.vendor_id` into the service container so controllers
 * can scope queries to the vendor's POs automatically.
 *
 * Usage: `Route::middleware('vendor_scope')`
 */
class VendorScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('vendor')) {
            return response()->json([
                'success'    => false,
                'error_code' => 'VENDOR_PORTAL_ACCESS_DENIED',
                'message'    => 'This endpoint is restricted to vendor portal users.',
            ], 403);
        }

        if (empty($user->vendor_id)) {
            return response()->json([
                'success'    => false,
                'error_code' => 'VENDOR_NOT_LINKED',
                'message'    => 'Your account is not linked to a vendor. Please contact your system administrator.',
            ], 403);
        }

        app()->instance('vendor_scope.vendor_id', (int) $user->vendor_id);

        return $next($request);
    }
}
