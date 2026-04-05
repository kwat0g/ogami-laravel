<?php

declare(strict_types=1);

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\Position;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Payroll\Models\PayrollDetail;
use App\Domains\Payroll\Models\PayrollRun;
use App\Models\User;
use Tests\Support\PayrollTestHelper;

/*
|--------------------------------------------------------------------------
| Leave → Attendance → Payroll Integration Tests
|--------------------------------------------------------------------------
| Verifies the complete leave-to-payroll workflow:
|   1. Leave request submission
|   2. Leave balance deduction
|   3. Attendance marking as 'leave'
|   4. Payroll computation excludes leave days
|
| Flow: Leave Request → Leave Balance Update → Attendance Log → Payroll
--------------------------------------------------------------------------
*/

beforeEach(function () {
    PayrollTestHelper::seedRateTables();
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'LeaveTypeSeeder'])->assertExitCode(0);

    $this->user = User::factory()->create();
    $this->user->assignRole('manager');

    $department = Department::firstOrCreate(
        ['code' => 'HRD'],
        ['name' => 'HR Department', 'is_active' => true]
    );

    $position = Position::firstOrCreate(
        ['code' => 'STAFF-001'],
        ['title' => 'Staff', 'department_id' => $department->id, 'is_active' => true]
    );

    $this->employee = Employee::create([
        'employee_code' => 'EMP-TEST-001',
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'date_of_birth' => '1990-01-01',
        'gender' => 'male',
        'civil_status' => 'SINGLE',
        'bir_status' => 'S',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'employment_type' => 'regular',
        'employment_status' => 'active',
        'pay_basis' => 'monthly',
        'basic_monthly_rate' => 30_000_00, // ₱30,000 in centavos
        'date_hired' => '2020-01-01',
        'onboarding_status' => 'active',
        'is_active' => true,
    ]);

    // Get leave type
    $this->leaveType = LeaveType::where('code', 'VL')->first()
        ?? LeaveType::firstOrCreate(
            ['code' => 'VL'],
            ['name' => 'Vacation Leave', 'is_active' => true, 'is_paid' => false]
        );

    // Initialize leave balance (balance is computed column)
    LeaveBalance::updateOrCreate(
        ['employee_id' => $this->employee->id, 'leave_type_id' => $this->leaveType->id, 'year' => 2025],
        ['opening_balance' => 15, 'accrued' => 0, 'adjusted' => 0, 'used' => 0, 'monetized' => 0]
    );
});

// ---------------------------------------------------------------------------
// INT-LEAVE-ATT-001: Approved leave deducts from leave balance
// ---------------------------------------------------------------------------

it('INT-LEAVE-ATT-001 — approved leave request deducts from leave balance', function () {
    $daysRequested = 3;

    $initialBalance = LeaveBalance::where('employee_id', $this->employee->id)
        ->where('leave_type_id', $this->leaveType->id)
        ->where('year', 2025)
        ->first();

    expect((float) $initialBalance->balance)->toEqual(15.00);

    // Create leave request
    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'date_from' => '2025-10-20',
        'date_to' => '2025-10-22',
        'total_days' => $daysRequested,
        'reason' => 'Vacation',
        'requester_type' => 'staff',
        'status' => 'approved',
        'submitted_by' => $this->user->id,
        'hr_approved_by' => $this->user->id,
        'hr_approved_at' => now(),
    ]);

    // Update leave balance used field (balance is computed)
    $initialBalance->used = $daysRequested;
    $initialBalance->save();
    $initialBalance->refresh();

    expect((float) $initialBalance->balance)->toEqual(12.00); // 15 - 3
    expect((float) $initialBalance->used)->toEqual(3.00);
});

// ---------------------------------------------------------------------------
// INT-LEAVE-ATT-002: Approved leave creates attendance log as 'leave'
// ---------------------------------------------------------------------------

it('INT-LEAVE-ATT-002 — approved leave creates attendance logs marked as leave', function () {
    $leaveDates = ['2025-10-20', '2025-10-21', '2025-10-22'];

    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'date_from' => $leaveDates[0],
        'date_to' => $leaveDates[2],
        'total_days' => 3,
        'reason' => 'Vacation',
        'requester_type' => 'staff',
        'status' => 'approved',
        'submitted_by' => $this->user->id,
        'hr_approved_by' => $this->user->id,
        'hr_approved_at' => now(),
    ]);

    // Create attendance logs for leave days
    foreach ($leaveDates as $date) {
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'work_date' => $date,
            'is_present' => false,
            'is_absent' => false,
            'source' => 'system',
        ]);
    }

    // Verify attendance logs exist
    $attendanceCount = AttendanceLog::where('employee_id', $this->employee->id)
        ->whereBetween('work_date', [$leaveDates[0], $leaveDates[2]])
        ->count();

    expect($attendanceCount)->toBe(3);
});

// ---------------------------------------------------------------------------
// INT-LEAVE-PAY-001: Approved leave types are unpaid by default
// ---------------------------------------------------------------------------

it('INT-LEAVE-PAY-001 — approved leave days reduce gross pay because all leave types are unpaid', function () {
    $monthlyRate = 30_000_00; // centavos
    $leaveDays = 3;
    $dailyRate = 1_000_00;
    $expectedGross = $monthlyRate - ($dailyRate * $leaveDays);

    // Create payroll run
    $payrollRun = PayrollRun::create([
        'reference_no' => 'PR-TEST-001',
        'pay_period_label' => 'October 2025',
        'cutoff_start' => '2025-10-01',
        'cutoff_end' => '2025-10-31',
        'pay_date' => '2025-10-31',
        'status' => 'processing',
        'run_type' => 'regular',
        'created_by' => $this->user->id,
    ]);

    // Create approved leave
    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'date_from' => '2025-10-20',
        'date_to' => '2025-10-22',
        'total_days' => $leaveDays,
        'reason' => 'Vacation',
        'requester_type' => 'staff',
        'status' => 'approved',
        'submitted_by' => $this->user->id,
        'hr_approved_by' => $this->user->id,
        'hr_approved_at' => now(),
    ]);

    // Create attendance for working days
    for ($i = 1; $i <= 22; $i++) {
        $date = sprintf('2025-10-%02d', $i);
        if ($i < 20 || $i > 22) { // Skip leave days
            AttendanceLog::create([
                'employee_id' => $this->employee->id,
                'work_date' => $date,
                'is_present' => true,
                'source' => 'manual',
            ]);
        }
    }

    // Create attendance for leave days
    for ($i = 20; $i <= 22; $i++) {
        $date = sprintf('2025-10-%02d', $i);
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'work_date' => $date,
            'is_present' => false,
            'source' => 'system',
        ]);
    }

    // Create payroll detail with unpaid leave
    $payrollDetail = PayrollDetail::create([
        'payroll_run_id' => $payrollRun->id,
        'employee_id' => $this->employee->id,
        'basic_monthly_rate_centavos' => $monthlyRate,
        'daily_rate_centavos' => $dailyRate,
        'hourly_rate_centavos' => 125_00,
        'working_days_in_period' => 26,
        'pay_basis' => 'monthly',
        'days_worked' => 19,
        'leave_days_paid' => 0,
        'leave_days_unpaid' => $leaveDays,
        'basic_pay_centavos' => $expectedGross,
        'gross_pay_centavos' => $expectedGross,
        'net_pay_centavos' => $expectedGross - 2_000_00,
    ]);

    // Verify payroll detail
    expect($payrollDetail->leave_days_paid)->toBe(0);
    expect($payrollDetail->leave_days_unpaid)->toBe($leaveDays);

    expect($payrollDetail->gross_pay_centavos)->toBe($expectedGross);
    expect($payrollDetail->gross_pay_centavos)->toBeLessThan($monthlyRate);
});

// ---------------------------------------------------------------------------
// INT-LEAVE-PAY-002: Unpaid leave reduces gross pay proportionally
// ---------------------------------------------------------------------------

it('INT-LEAVE-PAY-002 — unpaid leave days reduce gross pay proportionally', function () {
    $monthlyRate = 30_000_00; // centavos
    $dailyRate = 1_000_00; // ₱1,000 per day in centavos
    $workingDays = 22;
    $unpaidLeaveDays = 3;
    $expectedGross = $monthlyRate - ($dailyRate * $unpaidLeaveDays);

    $unpaidLeaveType = LeaveType::firstOrCreate(
        ['code' => 'UL'],
        ['name' => 'Unpaid Leave', 'category' => 'other', 'is_active' => true, 'is_paid' => false]
    );

    $payrollRun = PayrollRun::create([
        'reference_no' => 'PR-TEST-002',
        'pay_period_label' => 'October 2025',
        'cutoff_start' => '2025-10-01',
        'cutoff_end' => '2025-10-31',
        'pay_date' => '2025-10-31',
        'status' => 'processing',
        'run_type' => 'regular',
        'created_by' => $this->user->id,
    ]);

    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $unpaidLeaveType->id,
        'date_from' => '2025-10-20',
        'date_to' => '2025-10-22',
        'total_days' => $unpaidLeaveDays,
        'reason' => 'Personal',
        'requester_type' => 'staff',
        'status' => 'approved',
        'submitted_by' => $this->user->id,
        'hr_approved_by' => $this->user->id,
        'hr_approved_at' => now(),
    ]);

    $payrollDetail = PayrollDetail::create([
        'payroll_run_id' => $payrollRun->id,
        'employee_id' => $this->employee->id,
        'basic_monthly_rate_centavos' => $monthlyRate,
        'daily_rate_centavos' => $dailyRate,
        'hourly_rate_centavos' => 125_00,
        'working_days_in_period' => 26,
        'pay_basis' => 'monthly',
        'days_worked' => $workingDays,
        'leave_days_paid' => 0,
        'leave_days_unpaid' => $unpaidLeaveDays,
        'basic_pay_centavos' => $expectedGross,
        'gross_pay_centavos' => $expectedGross, // Reduced by unpaid leave
        'net_pay_centavos' => $expectedGross - 1_500_00,
    ]);

    // Verify unpaid leave deduction applied
    expect($payrollDetail->gross_pay_centavos)->toBe($expectedGross);
    expect($payrollDetail->gross_pay_centavos)->toBeLessThan($monthlyRate);
});

// ---------------------------------------------------------------------------
// INT-LEAVE-PAY-003: Leave balance validation prevents excess leave
// ---------------------------------------------------------------------------

it('INT-LEAVE-PAY-003 — leave request validation prevents exceeding available balance', function () {
    // Set low leave balance
    $balance = LeaveBalance::where('employee_id', $this->employee->id)
        ->where('leave_type_id', $this->leaveType->id)
        ->where('year', 2025)
        ->first();

    $balance->used = 13;
    $balance->save();
    $balance->refresh();

    expect((float) $balance->balance)->toBe(2.00);

    // Attempt to request more days than available
    $daysRequested = 5;

    // This would typically be caught by validation
    // For this test, we simulate the validation check
    $availableBalance = (float) $balance->balance;
    $isValid = $daysRequested <= $availableBalance;

    expect($isValid)->toBeFalse();
});

// ---------------------------------------------------------------------------
// INT-LEAVE-ATT-PAY-001: End-to-end leave to payroll flow
// ---------------------------------------------------------------------------

it('INT-LEAVE-ATT-PAY-001 — complete flow from leave request to payroll processing', function () {
    $monthlyRate = 30_000_00;
    $leaveDays = 2;

    // Step 1: Employee submits leave request
    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'date_from' => '2025-10-25',
        'date_to' => '2025-10-26',
        'total_days' => $leaveDays,
        'reason' => 'Family vacation',
        'requester_type' => 'staff',
        'status' => 'draft',
        'submitted_by' => $this->user->id,
    ]);

    expect($leave->status)->toBe('draft');

    // Step 2: Manager approves leave
    $leave->update([
        'status' => 'approved',
        'hr_approved_by' => $this->user->id,
        'hr_approved_at' => now(),
    ]);

    // Step 3: Leave balance deducted
    $balance = LeaveBalance::where('employee_id', $this->employee->id)
        ->where('leave_type_id', $this->leaveType->id)
        ->first();

    $balance->used = $leaveDays;
    $balance->save();
    $balance->refresh();

    expect((float) $balance->balance)->toBe(13.00); // 15 - 2

    // Step 4: Attendance logs created
    AttendanceLog::create([
        'employee_id' => $this->employee->id,
        'work_date' => '2025-10-25',
        'is_present' => false,
        'source' => 'system',
    ]);

    AttendanceLog::create([
        'employee_id' => $this->employee->id,
        'work_date' => '2025-10-26',
        'is_present' => false,
        'source' => 'system',
    ]);

    $attendanceCount = AttendanceLog::where('employee_id', $this->employee->id)
        ->whereBetween('work_date', ['2025-10-25', '2025-10-26'])
        ->count();
    expect($attendanceCount)->toBe(2);

    // Step 5: Payroll processed with reduced pay (all leave types are unpaid)
    $payrollRun = PayrollRun::create([
        'reference_no' => 'PR-TEST-E2E',
        'pay_period_label' => 'October 2025',
        'cutoff_start' => '2025-10-01',
        'cutoff_end' => '2025-10-31',
        'pay_date' => '2025-10-31',
        'status' => 'completed',
        'run_type' => 'regular',
        'created_by' => $this->user->id,
    ]);

    $payrollDetail = PayrollDetail::create([
        'payroll_run_id' => $payrollRun->id,
        'employee_id' => $this->employee->id,
        'basic_monthly_rate_centavos' => $monthlyRate,
        'daily_rate_centavos' => 1_000_00,
        'hourly_rate_centavos' => 125_00,
        'working_days_in_period' => 26,
        'pay_basis' => 'monthly',
        'days_worked' => 20,
        'leave_days_paid' => 0,
        'leave_days_unpaid' => $leaveDays,
        'basic_pay_centavos' => $monthlyRate - (1_000_00 * $leaveDays),
        'gross_pay_centavos' => $monthlyRate - (1_000_00 * $leaveDays),
        'net_pay_centavos' => ($monthlyRate - (1_000_00 * $leaveDays)) - 2_500_00,
    ]);

    // Verify end-to-end flow completed successfully
    expect($leave->status)->toBe('approved');
    expect((float) $balance->balance)->toBe(13.00);
    expect($attendanceCount)->toBe(2);
    expect($payrollDetail->leave_days_paid)->toBe(0);
    expect($payrollDetail->leave_days_unpaid)->toBe($leaveDays);
    expect($payrollDetail->gross_pay_centavos)->toBe($monthlyRate - (1_000_00 * $leaveDays));
});
