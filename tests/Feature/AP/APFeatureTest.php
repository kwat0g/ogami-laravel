<?php

declare(strict_types=1);

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'ap');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ModuleSeeder::class);
    $this->seed(\Database\Seeders\ModulePermissionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentPositionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentModuleAssignmentSeeder::class);
    $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

    // Get accounting department for RBAC v2
    $acctgDept = \App\Domains\HR\Models\Department::where('code', 'ACCTG')->first();

    $this->manager = User::factory()->create();
    $this->manager->assignRole('officer');
    $this->manager->departments()->attach($acctgDept->id, ['is_primary' => true]);

    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');
    $this->staff->departments()->attach($acctgDept->id, ['is_primary' => true]);

    $this->vendor = Vendor::create([
        'name'           => 'Test Vendor Co.',
        'contact_person' => 'Juan Cruz',
        'email'          => 'vendor@test.com',
        'phone'          => '09171234567',
        'address'        => '123 Test St.',
        'is_active'      => true,
        'created_by'     => $this->manager->id,
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
            'name'           => 'Another Vendor',
            'contact_person' => 'Maria',
            'email'          => 'another@test.com',
            'is_active'      => true,
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
    $apco = \App\Domains\Accounting\Models\ChartOfAccount::where('account_type', 'LIABILITY')->first();
    $exp = \App\Domains\Accounting\Models\ChartOfAccount::where('account_type', 'OPEX')->first();
    $period = \App\Domains\Accounting\Models\FiscalPeriod::create(['name' => 'Test 2026', 'code' => 'TEST-AP', 'date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'status' => 'open']);

    $this->actingAs($this->manager)
        ->postJson('/api/v1/accounting/ap/invoices', [
            'vendor_id'          => $this->vendor->id,
            'fiscal_period_id'   => $period->id,
            'ap_account_id'      => $apco->id,
            'expense_account_id' => $exp->id,
            'invoice_date'       => now()->toDateString(),
            'due_date'           => now()->addDays(30)->toDateString(),
            'net_amount'         => 50000.00,
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id']]);
});
