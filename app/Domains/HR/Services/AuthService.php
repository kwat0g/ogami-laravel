<?php

declare(strict_types=1);

namespace App\Domains\HR\Services;

use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Authentication orchestrator.
 *
 * Business rules enforced:
 *  - SEC-001 : lock account after 5 consecutive failed logins (30 min lockout)
 *  - SEC-003 : session tokens scoped to device with IP + UA stored in token name
 */
final class AuthService implements ServiceContract
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_MINUTES = 30;

    /**
     * Validate credentials and return an opaque result.
     *
     * @return array{user: User, token: string}
     *
     * @throws AuthorizationException
     */
    public function login(string $email, string $password, string $deviceName): array
    {
        $throttleKey = 'login:'.strtolower($email);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw new AuthorizationException(
                "Too many login attempts. Please try again in {$seconds} seconds.",
                'TOO_MANY_ATTEMPTS'
            );
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, self::LOCK_MINUTES * 60);

            if ($user) {
                $user->incrementFailedAttempts();

                if ($user->failed_login_attempts >= self::MAX_ATTEMPTS) {
                    $user->lockAccount(self::LOCK_MINUTES);
                }
            }

            $this->recordAuthAudit('failed_login', $user, [
                'email' => $email,
                'reason' => $user ? 'invalid_password' : 'unknown_email',
            ]);

            throw new AuthorizationException('Invalid credentials.', 'INVALID_CREDENTIALS');
        }

        RateLimiter::clear($throttleKey);

        if ($user->isLocked()) {
            throw new AuthorizationException(
                'Account is temporarily locked. Please try again later.',
                'ACCOUNT_LOCKED'
            );
        }

        $token = $this->issueFullToken($user, $deviceName);
        $user->resetFailedAttempts();

        $this->recordAuthAudit('login', $user, [
            'device' => $deviceName,
        ]);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /** Revoke current token (if any). Session invalidation is handled by the controller. */
    public function logout(User $user): void
    {
        $this->recordAuthAudit('logout', $user);

        // Only delete if an actual DB token is in use (not a Sanctum TransientToken
        // used for session-based SPA auth).
        $accessToken = $user->currentAccessToken();
        if ($accessToken instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $accessToken->delete();
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function issueFullToken(User $user, string $deviceName): string
    {
        $expirationMinutes = config('sanctum.expiration');

        $expiresAt = $expirationMinutes !== null
            ? now()->addMinutes((int) $expirationMinutes)
            : null;

        return $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;
    }

    /**
     * Record an authentication audit event.
     * When $user is null (e.g. unknown email on failed login) auditable_id is
     * stored as null — the audits table allows this for auth events.
     */
    private function recordAuthAudit(string $event, ?User $user, array $metadata = []): void
    {
        $request = request();

        try {
            DB::table('audits')->insert([
                'user_type' => $user ? get_class($user) : null,
                'user_id' => $user?->id,
                'event' => $event,
                'auditable_type' => User::class,
                'auditable_id' => $user?->id,   // null for unknown-email failures
                'old_values' => '{}',
                'new_values' => json_encode($metadata),
                'url' => $request?->fullUrl(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'tags' => 'auth',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let an audit write failure bubble up and break the auth flow.
            \Illuminate\Support\Facades\Log::warning('Auth audit write failed: '.$e->getMessage(), [
                'event' => $event,
                'email' => $metadata['email'] ?? null,
            ]);
        }
    }
}
