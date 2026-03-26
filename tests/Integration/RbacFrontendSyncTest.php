<?php

declare(strict_types=1);

/**
 * Integration Test: RBAC Backend-Frontend Synchronization
 *
 * Verifies that backend permissions are correctly reflected in the frontend.
 * These tests work in conjunction with Playwright E2E tests.
 *
 * Run with: ./vendor/bin/pest tests/Integration/RbacFrontendSyncTest.php
 */

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses()->group('integration', 'rbac', 'frontend-sync');
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'SalaryGradeSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder'])->assertExitCode(0);
});

// ═══════════════════════════════════════════════════════════════════════════════
// PERMISSION MATRIX CONSISTENCY TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('RBAC Frontend Sync — Permission Matrix', function () {

    test('production manager backend permissions match frontend expectations', function () {
        // Create Production Manager
        $user = User::factory()->create([
            'email' => 'test.prod.manager@ogamierp.local',
            'department_id' => 4, // Production
        ]);
        $user->assignRole('manager');

        // Create employee record
        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => 4,
            'position_id' => 20,
            'first_name' => 'Test',
            'last_name' => 'ProdManager',
        ]);

        // Backend assertions - what permissions they SHOULD have
        expect($user->hasPermissionTo('production.orders.view'))->toBeTrue();
        expect($user->hasPermissionTo('production.orders.create'))->toBeTrue();
        expect($user->hasPermissionTo('qc.inspections.view'))->toBeTrue();
        expect($user->hasPermissionTo('maintenance.view'))->toBeTrue();
        expect($user->hasPermissionTo('mold.view'))->toBeTrue();
        expect($user->hasPermissionTo('delivery.view'))->toBeTrue();
        expect($user->hasPermissionTo('iso.view'))->toBeTrue();

        // Backend assertions - what permissions they should NOT have (CRITICAL FIXES)
        expect($user->hasPermissionTo('payroll.view_runs'))->toBeFalse(
            'Production Manager should NOT have payroll.view_runs permission'
        );
        expect($user->hasPermissionTo('inventory.locations.manage'))->toBeFalse(
            'Production Manager should NOT have inventory.locations.manage permission'
        );
        expect($user->hasPermissionTo('chart_of_accounts.view'))->toBeFalse();
        expect($user->hasPermissionTo('vendors.view'))->toBeFalse();

        // Write permission state for frontend tests to verify
        $permissionState = [
            'role' => 'production_manager',
            'email' => $user->email,
            'should_see' => [
                'production.orders.view',
                'qc.inspections.view',
                'maintenance.view',
                'mold.view',
                'delivery.view',
                'iso.view',
            ],
            'should_not_see' => [
                'payroll.view_runs',
                'inventory.items.view',
                'chart_of_accounts.view',
            ],
        ];

        // Store for frontend test consumption
        $statePath = storage_path('testing/rbac_states');
        if (! is_dir($statePath)) {
            mkdir($statePath, 0755, true);
        }
        file_put_contents(
            $statePath.'/production_manager.json',
            json_encode($permissionState, JSON_PRETTY_PRINT)
        );

        expect(true)->toBeTrue();
    });

    test('accounting officer backend permissions match frontend expectations', function () {
        $user = User::factory()->create([
            'email' => 'test.acctg.officer@ogamierp.local',
            'department_id' => 3, // Accounting
        ]);
        $user->assignRole('officer');

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => 3,
            'first_name' => 'Test',
            'last_name' => 'AcctgOfficer',
        ]);

        // Should have accounting permissions
        expect($user->hasPermissionTo('chart_of_accounts.view'))->toBeTrue();
        expect($user->hasPermissionTo('journal_entries.view'))->toBeTrue();
        expect($user->hasPermissionTo('vendors.view'))->toBeTrue();
        expect($user->hasPermissionTo('vendor_invoices.view'))->toBeTrue();
        expect($user->hasPermissionTo('customers.view'))->toBeTrue();

        // CRITICAL FIX: Should have Banking permissions
        expect($user->hasPermissionTo('bank_accounts.view'))->toBeTrue(
            'Accounting Officer SHOULD have bank_accounts.view permission'
        );
        expect($user->hasPermissionTo('bank_reconciliations.view'))->toBeTrue(
            'Accounting Officer SHOULD have bank_reconciliations.view permission'
        );

        // Should NOT have payroll
        expect($user->hasPermissionTo('payroll.view_runs'))->toBeFalse();

        // Store state
        $permissionState = [
            'role' => 'accounting_officer',
            'email' => $user->email,
            'should_see' => [
                'chart_of_accounts.view',
                'journal_entries.view',
                'vendors.view',
                'bank_accounts.view',
                'bank_reconciliations.view',
            ],
            'should_not_see' => [
                'payroll.view_runs',
                'production.orders.view',
            ],
        ];

        $statePath = storage_path('testing/rbac_states');
        if (! is_dir($statePath)) {
            mkdir($statePath, 0755, true);
        }
        file_put_contents(
            $statePath.'/accounting_officer.json',
            json_encode($permissionState, JSON_PRETTY_PRINT)
        );

        expect(true)->toBeTrue();
    });

    test('warehouse head backend permissions match frontend expectations', function () {
        $user = User::factory()->create([
            'email' => 'test.wh.head@ogamierp.local',
            'department_id' => 7, // Warehouse
        ]);
        $user->assignRole('head');

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => 7,
            'first_name' => 'Test',
            'last_name' => 'WHHead',
        ]);

        // Should have inventory permissions
        expect($user->hasPermissionTo('inventory.items.view'))->toBeTrue();
        expect($user->hasPermissionTo('inventory.items.create'))->toBeTrue();
        expect($user->hasPermissionTo('inventory.stock.view'))->toBeTrue();
        expect($user->hasPermissionTo('inventory.mrq.view'))->toBeTrue();

        // CRITICAL FIX: Should have inventory management permissions
        expect($user->hasPermissionTo('inventory.locations.manage'))->toBeTrue(
            'Warehouse Head SHOULD have inventory.locations.manage permission'
        );

        // Should NOT have production or payroll
        expect($user->hasPermissionTo('production.orders.view'))->toBeFalse();
        expect($user->hasPermissionTo('payroll.view_runs'))->toBeFalse();

        // Store state
        $permissionState = [
            'role' => 'warehouse_head',
            'email' => $user->email,
            'should_see' => [
                'inventory.items.view',
                'inventory.items.create',
                'inventory.stock.view',
                'inventory.locations.manage',
                'inventory.mrq.view',
            ],
            'should_not_see' => [
                'production.orders.view',
                'payroll.view_runs',
                'chart_of_accounts.view',
            ],
        ];

        $statePath = storage_path('testing/rbac_states');
        if (! is_dir($statePath)) {
            mkdir($statePath, 0755, true);
        }
        file_put_contents(
            $statePath.'/warehouse_head.json',
            json_encode($permissionState, JSON_PRETTY_PRINT)
        );

        expect(true)->toBeTrue();
    });

    test('hr manager backend permissions match frontend expectations', function () {
        $user = User::factory()->create([
            'email' => 'test.hr.manager@ogamierp.local',
            'department_id' => 2, // HR
        ]);
        $user->assignRole('manager');

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => 2,
            'first_name' => 'Test',
            'last_name' => 'HRManager',
        ]);

        // Should have HR permissions
        expect($user->hasPermissionTo('hr.full_access'))->toBeTrue();
        expect($user->hasPermissionTo('employees.view_team'))->toBeTrue();
        expect($user->hasPermissionTo('attendance.view_team'))->toBeTrue();
        expect($user->hasPermissionTo('leaves.view_team'))->toBeTrue();

        // Should have Payroll
        expect($user->hasPermissionTo('payroll.view_runs'))->toBeTrue();
        expect($user->hasPermissionTo('payroll.initiate'))->toBeTrue();

        // Should NOT have accounting or production
        expect($user->hasPermissionTo('chart_of_accounts.view'))->toBeFalse();
        expect($user->hasPermissionTo('production.orders.view'))->toBeFalse();

        // Store state
        $permissionState = [
            'role' => 'hr_manager',
            'email' => $user->email,
            'should_see' => [
                'hr.full_access',
                'employees.view_team',
                'payroll.view_runs',
                'payroll.initiate',
            ],
            'should_not_see' => [
                'chart_of_accounts.view',
                'production.orders.view',
                'inventory.items.view',
            ],
        ];

        $statePath = storage_path('testing/rbac_states');
        if (! is_dir($statePath)) {
            mkdir($statePath, 0755, true);
        }
        file_put_contents(
            $statePath.'/hr_manager.json',
            json_encode($permissionState, JSON_PRETTY_PRINT)
        );

        expect(true)->toBeTrue();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// API RESPONSE CONSISTENCY TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('RBAC Frontend Sync — API Response Consistency', function () {

    test('auth me endpoint returns correct permissions for production manager', function () {
        $user = User::factory()->create([
            'email' => 'api.test.prod@ogamierp.local',
            'department_id' => 4,
        ]);
        $user->assignRole('manager');

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => 4,
            'first_name' => 'API',
            'last_name' => 'TestProd',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'email',
                    'roles',
                    'permissions',
                    'department_id',
                ],
            ]);

        $data = $response->json();

        // Verify permissions array matches expected
        $permissions = $data['user']['permissions'];

        expect(in_array('production.orders.view', $permissions))->toBeTrue();
        expect(in_array('qc.inspections.view', $permissions))->toBeTrue();

        // CRITICAL: Should NOT have payroll
        expect(in_array('payroll.view_runs', $permissions))->toBeFalse(
            'API should NOT return payroll.view_runs for Production Manager'
        );
    });

    test('auth me endpoint returns correct permissions for accounting officer', function () {
        $user = User::factory()->create([
            'email' => 'api.test.acctg@ogamierp.local',
            'department_id' => 3,
        ]);
        $user->assignRole('officer');

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => 3,
            'first_name' => 'API',
            'last_name' => 'TestAcctg',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $data = $response->json();
        $permissions = $data['user']['permissions'];

        // CRITICAL FIX: Should have banking
        expect(in_array('bank_accounts.view', $permissions))->toBeTrue(
            'API SHOULD return bank_accounts.view for Accounting Officer'
        );

        // Should NOT have payroll
        expect(in_array('payroll.view_runs', $permissions))->toBeFalse();
    });

    test('auth me endpoint returns correct permissions for warehouse head', function () {
        $user = User::factory()->create([
            'email' => 'api.test.wh@ogamierp.local',
            'department_id' => 7,
        ]);
        $user->assignRole('head');

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => 7,
            'first_name' => 'API',
            'last_name' => 'TestWH',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $data = $response->json();
        $permissions = $data['user']['permissions'];

        // CRITICAL FIX: Should have inventory management
        expect(in_array('inventory.locations.manage', $permissions))->toBeTrue(
            'API SHOULD return inventory.locations.manage for Warehouse Head'
        );

        // Should NOT have production
        expect(in_array('production.orders.view', $permissions))->toBeFalse();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// ROUTE ACCESS CONSISTENCY TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('RBAC Frontend Sync — Route Access Consistency', function () {

    test('production manager routes are correctly protected', function () {
        $user = User::factory()->create([
            'email' => 'route.test.prod@ogamierp.local',
            'department_id' => 4,
        ]);
        $user->assignRole('manager');

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => 4,
            'first_name' => 'Route',
            'last_name' => 'TestProd',
        ]);

        // Should access production routes
        $this->actingAs($user)
            ->getJson('/api/v1/production/orders')
            ->assertOk();

        // CRITICAL: Should NOT access payroll routes
        $this->actingAs($user)
            ->getJson('/api/v1/payroll/runs')
            ->assertForbidden();

        // Should NOT access accounting routes
        $this->actingAs($user)
            ->getJson('/api/v1/accounting/journal-entries')
            ->assertForbidden();

        // Should NOT access inventory categories
        $this->actingAs($user)
            ->getJson('/api/v1/inventory/categories')
            ->assertForbidden();
    });

    test('accounting officer routes are correctly protected', function () {
        $user = User::factory()->create([
            'email' => 'route.test.acctg@ogamierp.local',
            'department_id' => 3,
        ]);
        $user->assignRole('officer');

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => 3,
            'first_name' => 'Route',
            'last_name' => 'TestAcctg',
        ]);

        // Should access accounting routes
        $this->actingAs($user)
            ->getJson('/api/v1/accounting/journal-entries')
            ->assertOk();

        // CRITICAL FIX: Should access banking routes
        $this->actingAs($user)
            ->getJson('/api/v1/banking/accounts')
            ->assertOk();

        // Should NOT access payroll
        $this->actingAs($user)
            ->getJson('/api/v1/payroll/runs')
            ->assertForbidden();
    });

    test('warehouse head routes are correctly protected', function () {
        $user = User::factory()->create([
            'email' => 'route.test.wh@ogamierp.local',
            'department_id' => 7,
        ]);
        $user->assignRole('head');

        Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => 7,
            'first_name' => 'Route',
            'last_name' => 'TestWH',
        ]);

        // Should access inventory routes
        $this->actingAs($user)
            ->getJson('/api/v1/inventory/items')
            ->assertOk();

        // CRITICAL FIX: Should access categories
        $this->actingAs($user)
            ->getJson('/api/v1/inventory/categories')
            ->assertOk();

        // Should NOT access production
        $this->actingAs($user)
            ->getJson('/api/v1/production/orders')
            ->assertForbidden();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// CLEANUP
// ═══════════════════════════════════════════════════════════════════════════════

afterAll(function () {
    // Clean up test state files
    $statePath = storage_path('testing/rbac_states');
    if (is_dir($statePath)) {
        $files = glob($statePath.'/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($statePath);
    }
});
