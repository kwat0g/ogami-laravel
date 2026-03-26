<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AP\Models\Vendor;
use App\Domains\HR\Models\Department;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DepartmentModuleAssignmentSeeder;
use Database\Seeders\DepartmentPositionSeeder;
use Database\Seeders\ModulePermissionSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'ap');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);
    $this->seed(DepartmentPositionSeeder::class);
    $this->seed(DepartmentModuleAssignmentSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);

    // Get accounting department for RBAC v2
    $acctgDept = Department::where('code', 'ACCTG')->first();

    $this->manager = User::factory()->create();
    $this->manager->assignRole('officer');
    $this->manager->departments()->attach($acctgDept->id, ['is_primary' => true]);

    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');
    $this->staff->departments()->attach($acctgDept->id, ['is_primary' => true]);

    $this->vendor = Vendor::create([
        'name' => 'Test Vendor Co.',
        'contact_person' => 'Juan Cruz',
        'email' => 'vendor@test.com',
        'phone' => '09171234567',
        'address' => '123 Test St.',
        'is_active' => true,
        'created_by' => $this->manager->id,
    ]);
});

it('lists vendors with pagination', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/accounting/vendors')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

it('creates a vendor', function () {
    $this->actingAs($this->manager)
        ->postJson('/api/v1/accounting/vendors', [
            'name' => 'Another Vendor',
            'contact_person' => 'Maria',
            'email' => 'another@test.com',
            'is_active' => true,
            'is_ewt_subject' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Another Vendor');
});

it('shows a single vendor', function () {
    $this->actingAs($this->manager)
        ->getJson("/api/v1/accounting/vendors/{$this->vendor->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Test Vendor Co.');
});

it('lists vendor invoices', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/accounting/ap/invoices')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a vendor invoice', function () {
    $apco = ChartOfAccount::where('account_type', 'LIABILITY')->first();
    $exp = ChartOfAccount::where('account_type', 'OPEX')->first();
    $period = FiscalPeriod::create(['name' => 'Test 2026', 'code' => 'TEST-AP', 'date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'status' => 'open']);

    $this->actingAs($this->manager)
        ->postJson('/api/v1/accounting/ap/invoices', [
            'vendor_id' => $this->vendor->id,
            'fiscal_period_id' => $period->id,
            'ap_account_id' => $apco->id,
            'expense_account_id' => $exp->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'net_amount' => 50000.00,
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id']]);
});
