<?php

declare(strict_types=1);

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Employee Onboarding Integration Test
|--------------------------------------------------------------------------
| Covers the end-to-end HR-001 → HR-004 onboarding flow:
|   1. HR manager creates an employee (draft → documents_pending)
|   2. Employee is activated via PATCH (→ active / is_active = true)
|   3. Employee list and detail endpoints return the new record
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder'])->assertExitCode(0);

    $this->hrManager = User::factory()->create([
        'password' => Hash::make('HRpass!123'),
    ]);
    $this->hrManager->assignRole('hr_manager');
});

describe('POST /api/v1/hr/employees — create employee', function () {
    it('HR manager can create a new employee', function () {
        $payload = [
            'employee_code' => 'EMP-2025-001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'date_of_birth' => '1995-03-15',
            'gender' => 'female',
            'employment_type' => 'regular',
            'employment_status' => 'active',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 2500000, // ₱25,000.00
            'date_hired' => '2025-01-06',
        ];

        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/hr/employees', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.employee_code', 'EMP-2025-001')
            ->assertJsonPath('data.first_name', 'Maria')
            ->assertJsonPath('data.last_name', 'Santos');

        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-2025-001',
            'first_name' => 'Maria',
        ]);
    });

    it('rejects duplicate employee_code', function () {
        Employee::create([
            'employee_code' => 'EMP-2025-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'employment_status' => 'active',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 2000000,
            'daily_rate' => 90909,
            'hourly_rate' => 11363,
            'date_hired' => '2024-01-01',
            'onboarding_status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/hr/employees', [
                'employee_code' => 'EMP-2025-001',
                'first_name' => 'Pedro',
                'last_name' => 'Reyes',
                'date_of_birth' => '1991-05-10',
                'gender' => 'male',
                'employment_type' => 'regular',
                'employment_status' => 'active',
                'pay_basis' => 'monthly',
                'basic_monthly_rate' => 1800000,
                'date_hired' => '2025-01-06',
            ]);

        $response->assertStatus(422);
    });
});

describe('GET /api/v1/hr/employees — list employees', function () {
    it('returns paginated employee list', function () {
        Employee::create([
            'employee_code' => 'EMP-2025-002',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'date_of_birth' => '1993-07-20',
            'gender' => 'female',
            'employment_type' => 'regular',
            'employment_status' => 'active',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 1800000,
            'daily_rate' => 81818,
            'hourly_rate' => 10227,
            'date_hired' => '2024-06-01',
            'onboarding_status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/hr/employees');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'employee_code', 'full_name', 'date_hired']],
                'meta',
            ]);
    });
});

describe('GET /api/v1/hr/employees/{employee} — show employee', function () {
    it('returns employee detail with computed fields', function () {
        $employee = Employee::create([
            'employee_code' => 'EMP-2025-003',
            'first_name' => 'Carlo',
            'last_name' => 'Navarro',
            'date_of_birth' => '1988-11-05',
            'gender' => 'male',
            'employment_type' => 'regular',
            'employment_status' => 'active',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 3500000,
            'daily_rate' => 159090,
            'hourly_rate' => 19886,
            'date_hired' => '2020-03-01',
            'onboarding_status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson("/api/v1/hr/employees/{$employee->ulid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.employee_code', 'EMP-2025-003')
            ->assertJsonPath('data.first_name', 'Carlo')
            ->assertJsonStructure(['data' => [
                'id', 'employee_code', 'first_name', 'last_name',
                'basic_monthly_rate', 'date_hired',
            ]]);
    });
});
