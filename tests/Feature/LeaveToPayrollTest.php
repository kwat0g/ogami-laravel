<?php

declare(strict_types=1);

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Leave\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Leave → Payroll Integration Test
|--------------------------------------------------------------------------
| Covers LV-001 (submit) → LV-003 (approve) → LV-004 (balance deducted).
| Asserts the end-to-end leave lifecycle prior to payroll deduction.
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'LeaveTypeSeeder'])->assertExitCode(0);

    $this->hrManager = User::factory()->create([
        'password' => Hash::make('HRpass!123'),
    ]);
    $this->hrManager->assignRole('manager');

    // A separate user who submits (SoD: reviewer must differ from submitter)
    $this->submitter = User::factory()->create();

    // Minimal active employee
    $this->employee = Employee::create([
        'employee_code' => 'EMP-LEAVE-001',
        'first_name' => 'Luz',
        'last_name' => 'Bautista',
        'date_of_birth' => '1990-04-12',
        'gender' => 'female',
        'employment_type' => 'regular',
        'employment_status' => 'active',
        'pay_basis' => 'monthly',
        'basic_monthly_rate' => 2500000,
        'daily_rate' => 113636,
        'hourly_rate' => 14204,
        'date_hired' => '2022-01-03',
        'onboarding_status' => 'active',
        'is_active' => true,
    ]);

    // Give her SIL balance for the current year
    $this->leaveType = LeaveType::where('code', 'SIL')->first()
        ?? LeaveType::where('name', 'like', '%Service Incentive%')->first();

    if ($this->leaveType !== null) {
        LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'accrued' => 5.0,
            'used' => 0.0,
            'monetized' => 0.0,
            'forfeited' => 0.0,
            'carried_over' => 0.0,
        ]);
    }
});

describe('POST /api/v1/leave/requests — submit leave', function () {
    it('employee (via HR) can submit a leave request', function () {
        if ($this->leaveType === null) {
            $this->markTestSkipped('SIL leave type not found in seeder output.');
        }

        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/leave/requests', [
                'employee_id' => $this->employee->id,
                'leave_type_id' => $this->leaveType->id,
                'date_from' => now()->addDays(3)->toDateString(),
                'date_to' => now()->addDays(4)->toDateString(),
                'total_days' => 2,
                'is_half_day' => false,
                'reason' => 'Rest and recreation.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.total_days', 2);

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'status' => 'submitted',
        ]);
    });
});

describe('PATCH /api/v1/leave/requests/{id}/approve — approve leave', function () {
    it('HR manager can approve a pending leave and the balance is deducted', function () {
        if ($this->leaveType === null) {
            $this->markTestSkipped('SIL leave type not found in seeder output.');
        }

        // Create a leave request directly
        $leaveRequest = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'date_from' => now()->addDays(5)->toDateString(),
            'date_to' => now()->addDays(6)->toDateString(),
            'total_days' => 2,
            'is_half_day' => false,
            'status' => 'submitted',
            'submitted_by' => $this->submitter->id,
            'reason' => 'Integration test leave.',
        ]);

        $balanceBefore = LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->where('year', now()->year)
            ->value('used');

        $response = $this->actingAs($this->hrManager)
            ->patchJson("/api/v1/leave/requests/{$leaveRequest->id}/approve", [
                'remarks' => 'Approved for integration test.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // Balance should reflect the deduction
        $balanceAfter = LeaveBalance::where('employee_id', $this->employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->where('year', now()->year)
            ->value('used');

        expect((float) $balanceAfter)->toBeGreaterThan((float) $balanceBefore);
    });
});

describe('PATCH /api/v1/leave/requests/{id}/reject — reject leave', function () {
    it('HR manager can reject a pending leave request', function () {
        if ($this->leaveType === null) {
            $this->markTestSkipped('SIL leave type not found in seeder output.');
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'date_from' => now()->addDays(10)->toDateString(),
            'date_to' => now()->addDays(11)->toDateString(),
            'total_days' => 2,
            'is_half_day' => false,
            'status' => 'submitted',
            'submitted_by' => $this->submitter->id,
            'reason' => 'Reject test.',
        ]);

        $response = $this->actingAs($this->hrManager)
            ->patchJson("/api/v1/leave/requests/{$leaveRequest->id}/reject", [
                'remarks' => 'Insufficient balance.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'rejected',
        ]);
    });
});

describe('DELETE /api/v1/leave/requests/{id} — cancel leave', function () {
    it('a pending leave request can be cancelled', function () {
        if ($this->leaveType === null) {
            $this->markTestSkipped('SIL leave type not found in seeder output.');
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'date_from' => now()->addDays(20)->toDateString(),
            'date_to' => now()->addDays(20)->toDateString(),
            'total_days' => 1,
            'is_half_day' => false,
            'status' => 'submitted',
            'submitted_by' => $this->hrManager->id,
            'reason' => 'Cancel test.',
        ]);

        $response = $this->actingAs($this->hrManager)
            ->deleteJson("/api/v1/leave/requests/{$leaveRequest->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'cancelled',
        ]);
    });
});
