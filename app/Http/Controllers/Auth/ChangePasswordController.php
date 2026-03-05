<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Shared\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Handles authenticated password changes.
 *
 * SEC-007: Users must prove knowledge of their current password before
 * a new one is accepted.  The request is validated at FormRequest layer
 * (policy + format rules); this controller handles the hash verification
 * and persistence.
 */
class ChangePasswordController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/v1/auth/change-password
     *
     * Verifies the current password, then updates to the new one.
     * On success the response is 200; on wrong current password, 422.
     */
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse(
                'The current password you entered is incorrect.',
                'INVALID_CURRENT_PASSWORD',
                422,
                ['current_password' => ['The current password you entered is incorrect.']],
            );
        }

        if ($request->current_password === $request->password) {
            return $this->errorResponse(
                'Your new password must be different from your current password.',
                'SAME_PASSWORD',
                422,
                ['password' => ['Your new password must be different from your current password.']],
            );
        }

        $user->update([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
        ]);

        // Revoke all existing tokens — user must log in again with new password
        $user->tokens()->delete();

        return $this->successResponse(
            null,
            'Password changed successfully. Please log in with your new password.',
            200,
        );
    }
}
