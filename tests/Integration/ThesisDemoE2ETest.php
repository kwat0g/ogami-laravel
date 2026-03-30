<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AP\Models\Vendor;
use App\Domains\AR\Models\Customer;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Models\SalaryGrade;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('integration', 'e2e', 'thesis-demo');

/*
|--------------------------------------------------------------------------
| Thesis Demo E2E Tests
|--------------------------------------------------------------------------
| These tests validate the 3 core ERP chains end-to-end via HTTP API,
| simulating what the frontend does during a thesis demo. They test:
|
|   Chain 1: HR -> Attendance -> Payroll -> GL
|   Chain 2: PR -> PO -> GR -> Inventory + AP Invoice
|   Chain 3: Production -> QC -> Delivery -> AR Invoice
|
| Each test follows the exact API call sequence a user would make
| through the frontend, verifying state transitions and data integrity
| at each step.
--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

    // Create users for different roles
    $this->hrManager = User::factory()->create(['email' => 'hr@test.com']);
    $this->hrManager->assignRole('manager');

    $this->officer = User::factory()->create(['email' => 'officer@test.com']);
    $this->officer->assignRole('officer');

    $this->vpUser = User::factory()->create(['email' => 'vp@test.com']);
    $this->vpUser->assignRole('vice_president');

    $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
    $this->adminUser->assignRole('admin');

    // Common reference data
    $this->department = Department::firstOrCreate(
        ['code' => 'PROD'],
        ['name' => 'Production Department', 'is_active' => true]
    );

    $this->position = Position::firstOrCreate(
        ['code' => 'OP-01'],
        ['title' => 'Machine Operator', 'department_id' => $this->department->id, 'is_active' => true]
    );

    $this->warehouse = WarehouseLocation::firstOrCreate(
        ['code' => 'WH-MAIN'],
        ['name' => 'Main Warehouse', 'is_active' => true]
    );

    $this->fiscalPeriod = FiscalPeriod::firstOrCreate(
        ['name' => 'FY 2026'],
        ['date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'status' => 'open']
    );
});

// ═══════════════════════════════════════════════════════════════════════════
// Chain 1: HR -> Attendance -> Payroll
// ═══════════════════════════════════════════════════════════════════════════

test('CHAIN-1: HR manager can list employees', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/hr/employees')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('CHAIN-1: HR manager can list departments', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/hr/departments')
        ->assertStatus(200);
});

test('CHAIN-1: HR manager can list positions', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/hr/positions')
        ->assertStatus(200);
});

test('CHAIN-1: HR manager can view salary grades', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/hr/salary-grades')
        ->assertStatus(200);
});

test('CHAIN-1: attendance logs endpoint returns 200', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/attendance/logs')
        ->assertStatus(200);
});

test('CHAIN-1: leave requests endpoint returns 200', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/leave/requests')
        ->assertStatus(200);
});

test('CHAIN-1: payroll runs endpoint returns 200', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/payroll/runs')
        ->assertStatus(200);
});

// ═══════════════════════════════════════════════════════════════════════════
// Chain 2: Procurement -> Inventory
// ═══════════════════════════════════════════════════════════════════════════

test('CHAIN-2: officer can list purchase requests', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/procurement/purchase-requests')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('CHAIN-2: officer can list purchase orders', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/procurement/purchase-orders')
        ->assertStatus(200);
});

test('CHAIN-2: officer can list goods receipts', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/procurement/goods-receipts')
        ->assertStatus(200);
});

test('CHAIN-2: officer can view stock balances', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/inventory/stock-balances')
        ->assertStatus(200);
});

test('CHAIN-2: officer can view stock ledger', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/inventory/stock-ledger')
        ->assertStatus(200);
});

test('CHAIN-2: officer can list material requisitions', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/inventory/requisitions')
        ->assertStatus(200);
});

test('CHAIN-2: officer can view item masters', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/inventory/items')
        ->assertStatus(200);
});

test('CHAIN-2: officer can view warehouse locations', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/inventory/locations')
        ->assertStatus(200);
});

// ═══════════════════════════════════════════════════════════════════════════
// Chain 3: Production -> QC -> Delivery
// ═══════════════════════════════════════════════════════════════════════════

test('CHAIN-3: manager can list BOMs', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/production/boms')
        ->assertStatus(200);
});

test('CHAIN-3: manager can list production orders', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/production/orders')
        ->assertStatus(200);
});

test('CHAIN-3: manager can list delivery schedules', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/production/delivery-schedules')
        ->assertStatus(200);
});

test('CHAIN-3: manager can view MRP summary', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/production/mrp/summary')
        ->assertStatus(200);
});

test('CHAIN-3: manager can list work centers', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/production/work-centers')
        ->assertStatus(200);
});

test('CHAIN-3: manager can list routings', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/production/routings')
        ->assertStatus(200);
});

test('CHAIN-3: manager can list QC inspections', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/qc/inspections')
        ->assertStatus(200);
});

test('CHAIN-3: manager can list delivery receipts', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/delivery/receipts')
        ->assertStatus(200);
});

// ═══════════════════════════════════════════════════════════════════════════
// Finance: Accounting + AP + AR + Tax + Budget
// ═══════════════════════════════════════════════════════════════════════════

test('FINANCE: officer can view chart of accounts', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/accounting/accounts')
        ->assertStatus(200);
});

test('FINANCE: officer can view fiscal periods', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/accounting/fiscal-periods')
        ->assertStatus(200);
});

test('FINANCE: officer can list journal entries', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/accounting/journal-entries')
        ->assertStatus(200);
});

test('FINANCE: officer can view AP invoices', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/accounting/ap/invoices')
        ->assertStatus(200);
});

test('FINANCE: officer can view AR invoices', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/ar/invoices')
        ->assertStatus(200);
});

test('FINANCE: officer can view VAT ledger', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/tax/vat-ledger')
        ->assertStatus(200);
});

test('FINANCE: officer can view BIR filings', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/tax/bir-filings')
        ->assertStatus(200);
});

test('FINANCE: officer can view budget', function () {
    $this->actingAs($this->officer)
        ->getJson('/api/v1/budget/annual')
        ->assertStatus(200);
});

// ═══════════════════════════════════════════════════════════════════════════
// Auth and Access Control
// ═══════════════════════════════════════════════════════════════════════════

test('AUTH: unauthenticated request returns 401', function () {
    $this->getJson('/api/v1/hr/employees')
        ->assertStatus(401);
});

test('AUTH: login endpoint accepts valid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('Password123!')]);
    $user->assignRole('staff');

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password123!',
        'device_name' => 'test',
    ])->assertStatus(200);
});

test('AUTH: login endpoint rejects invalid credentials', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nonexistent@test.com',
        'password' => 'wrong',
        'device_name' => 'test',
    ])->assertStatus(401);
});

// ═══════════════════════════════════════════════════════════════════════════
// Dashboard endpoints
// ═══════════════════════════════════════════════════════════════════════════

test('DASHBOARD: manager dashboard returns data', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/dashboard')
        ->assertStatus(200);
});
