<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\HR\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\UserPermissionsResource;
use App\Shared\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * Thin auth controller — all logic lives in AuthService.
 * Each action ≤ 15 lines of business logic.
 */
class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $authService) {}

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->email,
            $request->password,
            $request->deviceName()
        );

        // Establish a session for SPA (browser) clients.
        // Token-based clients (tests, API, mobile) continue to use Bearer tokens.
        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            Auth::guard('web')->login($result['user']);
        }

        return $this->successResponse([
            'token' => $result['token'],
            'user' => new UserPermissionsResource($result['user']),
        ], 'Login successful.', 200);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Remove user from active users tracking
        if ($user) {
            Redis::del("user_activity:{$user->id}");
        }

        $this->authService->logout($user);

        $response = $this->successResponse([], 'Logged out successfully.', 200);

        // Invalidate the session for SPA clients and expire their cookies so
        // the browser removes them immediately rather than waiting for the
        // browser session to end.
        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Expire the session cookie and the XSRF-TOKEN cookie.
            // Cookie::forget() sends a Set-Cookie header with expire=past,
            // which instructs the browser to delete the cookie.
            $sessionCookieName = config('session.cookie', 'laravel_session');
            $response->withCookie(Cookie::forget($sessionCookieName));
            $response->withCookie(Cookie::forget('XSRF-TOKEN'));
        }

        return $response;
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserPermissionsResource($request->user())
        );
    }

    /**
     * GET /api/v1/auth/me/permissions
     * Convenience endpoint — returns the flat permissions array only.
     */
    public function permissions(Request $request): JsonResponse
    {
        return $this->successResponse(
            $request->user()->getAllPermissions()->pluck('name')->values()
        );
    }
}
