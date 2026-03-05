<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Auth Login Feature Tests
|--------------------------------------------------------------------------
| Tests: SEC-001 lockout, happy path, wrong credentials.
--------------------------------------------------------------------------
*/

beforeEach(function () {
    // Seed roles/permissions so the User model and HasRoles work
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
});

describe('POST /api/v1/auth/login', function () {
    it('returns token on valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'staff@test.local',
            'password' => Hash::make('ValidPass!123'),
        ]);
        $user->assignRole('hr_manager');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@test.local',
            'password' => 'ValidPass!123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['token', 'user' => ['id', 'roles', 'permissions']],
            ]);
    });

    it('returns 403 on wrong password', function () {
        User::factory()->create([
            'email' => 'bad@test.local',
            'password' => Hash::make('CorrectPass!123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'bad@test.local',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
    });

    it('locks account after 5 consecutive failures — SEC-001', function () {
        $user = User::factory()->create([
            'email' => 'lockme@test.local',
            'password' => Hash::make('RealPass!123'),
        ]);
        $user->assignRole('hr_manager');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'lockme@test.local',
                'password' => 'WrongPass!',
            ]);
        }

        // 6th attempt with a correct password should still return locked
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'lockme@test.local',
            'password' => 'RealPass!123',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['error_code' => 'TOO_MANY_ATTEMPTS']);
    });

    it('returns 422 on missing email', function () {
        $this->postJson('/api/v1/auth/login', ['password' => 'anything'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR');
    });
});
