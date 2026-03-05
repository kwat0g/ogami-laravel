<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Change Password Controller — Feature Tests
|--------------------------------------------------------------------------
| Covers:
|   - Successful password change (200 + tokens revoked)
|   - Wrong current password (422 INVALID_CURRENT_PASSWORD)
|   - New password same as current (422 SAME_PASSWORD)
|   - Unauthenticated request (401)
|   - Weak new password fails validation (422)
--------------------------------------------------------------------------
*/

const VALID_PASSWORD = 'OldPass!1234';
const NEW_PASSWORD = 'NewPass@5678';

function makeAuthUser(): User
{
    $user = User::factory()->create([
        'password' => Hash::make(VALID_PASSWORD),
    ]);

    return $user;
}

describe('POST /api/v1/auth/change-password', function () {
    it('returns 200 and revokes all tokens on successful change', function () {
        $user = makeAuthUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => VALID_PASSWORD,
                'password' => NEW_PASSWORD,
                'password_confirmation' => NEW_PASSWORD,
            ]);

        $response->assertStatus(200);

        // Confirm password was actually updated
        $user->refresh();
        expect(Hash::check(NEW_PASSWORD, $user->password))->toBeTrue();

        // All tokens should have been revoked
        expect($user->tokens()->count())->toBe(0);
    });

    it('returns 422 INVALID_CURRENT_PASSWORD when current password is wrong', function () {
        $user = makeAuthUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'WrongPassword!99',
                'password' => NEW_PASSWORD,
                'password_confirmation' => NEW_PASSWORD,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_CURRENT_PASSWORD');
    });

    it('returns 422 SAME_PASSWORD when new password matches current password', function () {
        $user = makeAuthUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => VALID_PASSWORD,
                'password' => VALID_PASSWORD,
                'password_confirmation' => VALID_PASSWORD,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'SAME_PASSWORD');
    });

    it('returns 401 for unauthenticated requests', function () {
        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => VALID_PASSWORD,
            'password' => NEW_PASSWORD,
            'password_confirmation' => NEW_PASSWORD,
        ]);

        $response->assertStatus(401);
    });

    it('returns 422 when new password does not meet complexity rules (no uppercase)', function () {
        $user = makeAuthUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => VALID_PASSWORD,
                'password' => 'weakpass!123',
                'password_confirmation' => 'weakpass!123',
            ]);

        // Fails FormRequest validation — missing uppercase letter
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    it('returns 422 when password_confirmation does not match', function () {
        $user = makeAuthUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => VALID_PASSWORD,
                'password' => NEW_PASSWORD,
                'password_confirmation' => 'different!999',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    it('updates password_changed_at timestamp on success', function () {
        $user = makeAuthUser();
        $user->update(['password_changed_at' => null]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => VALID_PASSWORD,
                'password' => NEW_PASSWORD,
                'password_confirmation' => NEW_PASSWORD,
            ])
            ->assertStatus(200);

        $user->refresh();
        expect($user->password_changed_at)->not->toBeNull();
    });
});
