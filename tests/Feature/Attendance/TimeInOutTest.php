<?php

declare(strict_types=1);

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\Attendance\Models\EmployeeShiftAssignment;
use App\Domains\Attendance\Models\EmployeeWorkLocation;
use App\Domains\Attendance\Models\ShiftSchedule;
use App\Domains\Attendance\Models\WorkLocation;
use App\Domains\HR\Models\Employee;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
});

function createEmployeeWithShiftAndLocation(): array
{
    $user = User::factory()->create();
    $user->assignRole('staff');

    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'employment_status' => 'active',
    ]);

    $shift = ShiftSchedule::create([
        'code' => 'SHIFT-TEST',
        'name' => 'Day Shift',
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'break_minutes' => 60,
        'work_days' => '1,2,3,4,5',
        'grace_period_minutes' => 10,
        'is_active' => true,
    ]);

    EmployeeShiftAssignment::create([
        'employee_id' => $employee->id,
        'shift_schedule_id' => $shift->id,
        'effective_from' => now()->subYear()->toDateString(),
        'assigned_by' => $user->id,
    ]);

    // Work location in Metro Manila
    $location = WorkLocation::create([
        'name' => 'Main Office',
        'code' => 'MAIN',
        'address' => '123 Ayala Ave, Makati',
        'city' => 'Makati',
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'radius_meters' => 200,
        'allowed_variance_meters' => 20,
        'is_remote_allowed' => false,
        'is_active' => true,
    ]);

    EmployeeWorkLocation::create([
        'employee_id' => $employee->id,
        'work_location_id' => $location->id,
        'effective_date' => now()->subYear()->toDateString(),
        'is_primary' => true,
        'assigned_by' => $user->id,
    ]);

    return [$user, $employee, $shift, $location];
}

it('allows employee to time in within geofence', function () {
    [$user, $employee, $shift, $location] = createEmployeeWithShiftAndLocation();

    $response = $this->actingAs($user)->postJson('/api/v1/attendance/time-in', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
        'device_info' => ['userAgent' => 'PHPUnit'],
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.attendance_status', 'pending');

    $log = AttendanceLog::where('employee_id', $employee->id)
        ->where('work_date', today()->toDateString())
        ->first();

    expect($log)->not->toBeNull();
    expect($log->time_in)->not->toBeNull();
    expect($log->source)->toBe('web_clock');
    expect($log->time_in_within_geofence)->toBeTrue();
    expect($log->is_flagged)->toBeFalse();
});

it('records correct geolocation data on time in', function () {
    [$user, $employee] = createEmployeeWithShiftAndLocation();

    $this->actingAs($user)->postJson('/api/v1/attendance/time-in', [
        'latitude' => 14.5548,
        'longitude' => 121.0245,
        'accuracy_meters' => 15.5,
        'device_info' => ['browser' => 'Chrome'],
    ])->assertStatus(201);

    $log = AttendanceLog::where('employee_id', $employee->id)->first();

    expect((float) $log->time_in_latitude)->toBeGreaterThan(14.0);
    expect((float) $log->time_in_longitude)->toBeGreaterThan(121.0);
    expect((float) $log->time_in_accuracy_meters)->toBe(15.5);
    expect($log->time_in_device_info)->toBeArray();
    expect($log->time_in_device_info['browser'])->toBe('Chrome');
});

it('prevents duplicate time in on same day', function () {
    [$user, $employee] = createEmployeeWithShiftAndLocation();

    $this->actingAs($user)->postJson('/api/v1/attendance/time-in', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
    ])->assertStatus(201);

    $response = $this->actingAs($user)->postJson('/api/v1/attendance/time-in', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('ALREADY_TIMED_IN');
});

it('throws OUTSIDE_GEOFENCE when outside geofence without reason', function () {
    [$user] = createEmployeeWithShiftAndLocation();

    // Location ~5km away from Makati office
    $response = $this->actingAs($user)->postJson('/api/v1/attendance/time-in', [
        'latitude' => 14.6000,
        'longitude' => 121.0800,
        'accuracy_meters' => 10,
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('OUTSIDE_GEOFENCE');
});

it('allows time in outside geofence with override reason and flags record', function () {
    [$user, $employee] = createEmployeeWithShiftAndLocation();

    $response = $this->actingAs($user)->postJson('/api/v1/attendance/time-in', [
        'latitude' => 14.6000,
        'longitude' => 121.0800,
        'accuracy_meters' => 10,
        'override_reason' => 'Client meeting at BGC office',
    ]);

    $response->assertStatus(201);

    $log = AttendanceLog::where('employee_id', $employee->id)->first();

    expect($log->time_in_within_geofence)->toBeFalse();
    expect($log->is_flagged)->toBeTrue();
    expect($log->time_in_override_reason)->toBe('Client meeting at BGC office');
});

it('throws NO_SHIFT_ASSIGNED when no shift exists', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'employment_status' => 'active',
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/attendance/time-in', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('NO_SHIFT_ASSIGNED');
});

it('allows employee to time out after time in', function () {
    [$user, $employee] = createEmployeeWithShiftAndLocation();

    $this->actingAs($user)->postJson('/api/v1/attendance/time-in', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
    ])->assertStatus(201);

    $response = $this->actingAs($user)->postJson('/api/v1/attendance/time-out', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
    ]);

    $response->assertStatus(200);

    $log = AttendanceLog::where('employee_id', $employee->id)->first();

    expect($log->time_out)->not->toBeNull();
    expect($log->worked_minutes)->toBeGreaterThan(0);
    expect($log->is_present)->toBeTrue();
    expect($log->attendance_status)->not->toBe('pending');
});

it('cannot time out without timing in first', function () {
    [$user] = createEmployeeWithShiftAndLocation();

    $response = $this->actingAs($user)->postJson('/api/v1/attendance/time-out', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('NOT_TIMED_IN');
});

it('cannot time out twice', function () {
    [$user] = createEmployeeWithShiftAndLocation();

    $this->actingAs($user)->postJson('/api/v1/attendance/time-in', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
    ])->assertStatus(201);

    $this->actingAs($user)->postJson('/api/v1/attendance/time-out', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
    ])->assertStatus(200);

    $response = $this->actingAs($user)->postJson('/api/v1/attendance/time-out', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('ALREADY_TIMED_OUT');
});

it('returns today status for authenticated employee', function () {
    [$user, $employee] = createEmployeeWithShiftAndLocation();

    // Before time-in, today returns null
    $response = $this->actingAs($user)->getJson('/api/v1/attendance/today');
    $response->assertOk();
    $response->assertJsonPath('data', null);

    // After time-in, today returns the log
    $this->actingAs($user)->postJson('/api/v1/attendance/time-in', [
        'latitude' => 14.5547,
        'longitude' => 121.0244,
        'accuracy_meters' => 10,
    ])->assertStatus(201);

    $response = $this->actingAs($user)->getJson('/api/v1/attendance/today');
    $response->assertOk();
    $response->assertJsonPath('data.attendance_status', 'pending');
    $response->assertJsonPath('data.source', 'web_clock');
});
