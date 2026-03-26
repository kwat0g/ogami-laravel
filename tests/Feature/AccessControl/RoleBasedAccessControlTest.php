<?php

declare(strict_types=1);

/**
 * Feature Tests: Role-Based Access Control
 *
 * Verifies that each role can access only their permitted routes.
 * Uses existing test accounts from ManufacturingEmployeeSeeder.
 *
 * NON-ADMIN ROLES ONLY
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses()->group('feature', 'access-control', 'rbac');
uses(RefreshDatabase::class);

beforeEach(function () {
    // Use DatabaseSeeder which has proper ordering
    $this->artisan('db:seed')->assertExitCode(0);

    // Seed additional RBAC v2 configuration
    $this->artisan('db:seed', ['--class' => 'ModuleSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ModulePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'DepartmentModuleAssignmentSeeder'])->assertExitCode(0);

    // Clear permission cache
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

function getUserByEmail(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

// ═══════════════════════════════════════════════════════════════════════════════
// HR MODULE ACCESS TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('HR Module — Access Control', function () {

    // ALLOWED: hr_manager
    test('hr_manager can access HR routes', function () {
        $user = getUserByEmail('hr.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/hr/employees')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/hr/attendance')
            ->assertOk();
    });

    // ALLOWED: hr_officer (limited)
    test('hr_officer can access limited HR routes', function () {
        $user = getUserByEmail('ga.officer@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/hr/employees')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/hr/attendance')
            ->assertOk();
    });

    // ALLOWED: acctg_manager (managers can view employees across departments)
    test('acctg_manager CAN view employees (cross-department)', function () {
        $user = getUserByEmail('acctg.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/hr/employees')
            ->assertOk();
    });

    // ALLOWED: prod_manager (managers can view employees across departments)
    test('prod_manager CAN view employees (cross-department)', function () {
        $user = getUserByEmail('prod.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/hr/employees')
            ->assertOk();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// PAYROLL MODULE ACCESS TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('Payroll Module — Access Control', function () {

    // ALLOWED: hr_manager
    test('hr_manager can access Payroll routes', function () {
        $user = getUserByEmail('hr.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/payroll/runs')
            ->assertOk();
    });

    // ALLOWED: hr_officer (view only)
    test('hr_officer can view payroll runs', function () {
        $user = getUserByEmail('ga.officer@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/payroll/runs')
            ->assertOk();
    });

    // CRITICAL FIX: DENIED - prod_manager should NOT access Payroll
    test('prod_manager cannot access Payroll routes', function () {
        $user = getUserByEmail('prod.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/payroll/runs')
            ->assertForbidden();
    });

    // DENIED: acctg_officer
    test('acctg_officer cannot access Payroll routes', function () {
        $user = getUserByEmail('acctg.officer@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/payroll/runs')
            ->assertForbidden();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// ACCOUNTING MODULE ACCESS TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('Accounting Module — Access Control', function () {

    // ALLOWED: acctg_manager
    test('acctg_manager can access Accounting routes', function () {
        $user = getUserByEmail('acctg.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/accounting/journal-entries')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/accounting/accounts')
            ->assertOk();
    });

    // ALLOWED: acctg_officer
    test('acctg_officer can access Accounting routes', function () {
        $user = getUserByEmail('acctg.officer@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/accounting/journal-entries')
            ->assertOk();
    });

    // DENIED: hr_manager
    test('hr_manager cannot access Accounting routes', function () {
        $user = getUserByEmail('hr.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/accounting/journal-entries')
            ->assertForbidden();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// BANKING MODULE ACCESS TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('Banking Module — Access Control (CRITICAL FIX)', function () {

    // ALLOWED: acctg_manager
    test('acctg_manager can access Banking routes', function () {
        $user = getUserByEmail('acctg.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/accounting/bank-accounts')
            ->assertOk();
    });

    // ALLOWED - acctg_officer can VIEW banking (but not create/manage)
    test('acctg_officer CAN view Banking accounts', function () {
        $user = getUserByEmail('acctg.officer@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/accounting/bank-accounts')
            ->assertOk();
    });

    // DENIED: hr_manager
    test('hr_manager cannot access Banking routes', function () {
        $user = getUserByEmail('hr.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/accounting/bank-accounts')
            ->assertForbidden();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// PRODUCTION MODULE ACCESS TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('Production Module — Access Control', function () {

    // ALLOWED: prod_manager
    test('prod_manager can access Production routes', function () {
        $user = getUserByEmail('prod.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/production/orders')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/production/boms')
            ->assertOk();
    });

    // ALLOWED: plant_manager
    test('plant_manager can access Production routes', function () {
        $user = getUserByEmail('plant.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/production/orders')
            ->assertOk();
    });

    // DENIED: hr_manager
    test('hr_manager cannot access Production routes', function () {
        $user = getUserByEmail('hr.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/production/orders')
            ->assertForbidden();
    });

    // DENIED: acctg_manager
    test('acctg_manager cannot access Production routes', function () {
        $user = getUserByEmail('acctg.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/production/orders')
            ->assertForbidden();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// INVENTORY MODULE ACCESS TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('Inventory Module — Access Control (CRITICAL FIX)', function () {

    // ALLOWED: wh_head
    test('wh_head can access Inventory routes', function () {
        $user = getUserByEmail('warehouse.head@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/inventory/items')
            ->assertOk();
    });

    // CRITICAL FIX: ALLOWED - wh_head should manage Categories
    test('wh_head CAN access Inventory Categories', function () {
        $user = getUserByEmail('warehouse.head@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/inventory/items/categories')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/inventory/locations')
            ->assertOk();
    });

    // DENIED: prod_manager (only view, no management)
    test('prod_manager cannot access Inventory Categories', function () {
        $user = getUserByEmail('prod.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/inventory/items/categories')
            ->assertForbidden();
    });

    // DENIED: acctg_manager
    test('acctg_manager cannot access Inventory routes', function () {
        $user = getUserByEmail('acctg.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/inventory/items')
            ->assertForbidden();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// PROCUREMENT MODULE ACCESS TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('Procurement Module — Access Control', function () {

    // ALLOWED: purchasing_officer
    test('purchasing_officer can access Procurement routes', function () {
        $user = getUserByEmail('purchasing.officer@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/procurement/purchase-requests')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/procurement/purchase-orders')
            ->assertOk();
    });

    // DENIED: hr_manager
    test('hr_manager cannot access Procurement routes', function () {
        $user = getUserByEmail('hr.manager@ogamierp.local');

        $this->actingAs($user)
            ->getJson('/api/v1/procurement/purchase-requests')
            ->assertForbidden();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// CROSS-CUTTING TESTS
// ═══════════════════════════════════════════════════════════════════════════════

describe('Cross-Cutting — Permission Consistency', function () {

    test('unauthenticated users cannot access any protected route', function () {
        $this->getJson('/api/v1/hr/employees')->assertUnauthorized();
        $this->getJson('/api/v1/payroll/runs')->assertUnauthorized();
        $this->getJson('/api/v1/accounting/journal-entries')->assertUnauthorized();
        $this->getJson('/api/v1/production/orders')->assertUnauthorized();
        $this->getJson('/api/v1/inventory/items')->assertUnauthorized();
    });

    test('roles only see their department modules', function () {
        // Sales manager should be blocked from accounting/production management
        $blockedModules = [
            ['route' => '/api/v1/accounting/journal-entries', 'name' => 'Accounting'],
            ['route' => '/api/v1/production/orders', 'name' => 'Production'],
        ];

        $salesUser = getUserByEmail('crm.manager@ogamierp.local');

        foreach ($blockedModules as $module) {
            $this->actingAs($salesUser)
                ->getJson($module['route'])
                ->assertForbidden();
        }

        // Sales manager CAN access CRM routes (their module)
        $this->actingAs($salesUser)
            ->getJson('/api/v1/crm/tickets')
            ->assertOk();
    });
});
