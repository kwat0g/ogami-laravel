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
    $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('accounting_manager');

    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');

    $this->vendor = Vendor::create([
        'code'         => 'VND-001',
        'company_name' => 'Test Vendor Co.',
        'contact_name' => 'Juan Cruz',
        'email'        => 'vendor@test.com',
        'phone'        => '09171234567',
        'address'      => '123 Test St.',
        'is_active'    => true,
        'created_by_id' => $this->manager->id,
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
            'code'         => 'VND-002',
            'company_name' => 'Another Vendor',
            'contact_name' => 'Maria',
            'email'        => 'another@test.com',
            'is_active'    => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.company_name', 'Another Vendor');
});

it('shows a single vendor', function () {
    $this->actingAs($this->manager)
        ->getJson("/api/v1/accounting/vendors/{$this->vendor->ulid}")
        ->assertOk()
        ->assertJsonPath('data.code', 'VND-001');
});

it('lists vendor invoices', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/accounting/ap/invoices')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a vendor invoice', function () {
    $this->actingAs($this->manager)
        ->postJson('/api/v1/accounting/ap/invoices', [
            'vendor_id'      => $this->vendor->id,
            'invoice_number' => 'VI-001',
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'total_amount'   => 50000.00,
            'currency'       => 'PHP',
        ])
        ->assertCreated()
        ->assertJsonPath('data.invoice_number', 'VI-001');
});
