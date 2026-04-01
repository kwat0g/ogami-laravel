<?php

declare(strict_types=1);

use App\Domains\HR\Models\Employee;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Employee Lifecycle — Termination / Archive
|--------------------------------------------------------------------------
| Enforces lifecycle policy:
|   - No direct delete endpoint for employee records
|   - Transitioning to `terminated` archives (soft-deletes) the record
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

describe('employee delete action is not exposed', function () {
    it('returns 405 for DELETE /api/v1/hr/employees/{employee}', function () {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/hr/employees/{$employee->ulid}")
            ->assertStatus(405);
    });
});

describe('employee termination lifecycle', function () {
    it('transitions active employee to terminated and archives the record', function () {
        $employee = Employee::factory()->create([
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/hr/employees/{$employee->ulid}/transition", [
                'to_state' => 'terminated',
                'separation_date' => now()->toDateString(),
                'separation_reason' => 'Policy violation',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.employment_status', 'terminated')
            ->assertJsonPath('data.is_active', false);

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'employment_status' => 'terminated',
            'is_active' => false,
        ]);
    });
});
