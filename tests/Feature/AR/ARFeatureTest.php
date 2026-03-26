<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AR\Models\Customer;
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
uses()->group('feature', 'ar');

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

    $this->customer = Customer::create([
        'name' => 'Acme Corp',
        'contact_person' => 'John Doe',
        'email' => 'john@acme.com',
        'phone' => '09171234567',
        'address' => '456 Client Ave.',
        'is_active' => true,
        'created_by' => $this->manager->id,
    ]);
});

it('lists customers', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/ar/customers')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a customer', function () {
    $this->actingAs($this->manager)
        ->postJson('/api/v1/ar/customers', [
            'name' => 'Beta Inc',
            'contact_person' => 'Jane',
            'email' => 'jane@beta.com',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Beta Inc');
});

it('shows a single customer', function () {
    $this->actingAs($this->manager)
        ->getJson("/api/v1/ar/customers/{$this->customer->ulid}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme Corp');
});

it('lists customer invoices', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/ar/invoices')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a customer invoice', function () {
    $arco = ChartOfAccount::where('account_type', 'ASSET')->first();
    $rev = ChartOfAccount::where('account_type', 'REVENUE')->first();
    $period = FiscalPeriod::create(['name' => 'Test 2026 AR', 'code' => 'TEST-AR', 'date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'status' => 'open']);

    $this->actingAs($this->manager)
        ->postJson('/api/v1/ar/invoices', [
            'customer_id' => $this->customer->id,
            'fiscal_period_id' => $period->id,
            'ar_account_id' => $arco->id,
            'revenue_account_id' => $rev->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 75000.00,
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id']]);
});

it('returns AR aging report', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/ar/aging-report')
        ->assertOk()
        ->assertJsonStructure(['data', 'as_of_date']);
});
