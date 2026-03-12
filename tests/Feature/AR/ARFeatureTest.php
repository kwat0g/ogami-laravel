<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'ar');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('accounting_manager');

    $this->customer = Customer::create([
        'company_name' => 'Acme Corp',
        'contact_name' => 'John Doe',
        'email'        => 'john@acme.com',
        'phone'        => '09171234567',
        'address'      => '456 Client Ave.',
        'is_active'    => true,
        'created_by_id' => $this->manager->id,
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
            'company_name' => 'Beta Inc',
            'contact_name' => 'Jane',
            'email'        => 'jane@beta.com',
            'is_active'    => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.company_name', 'Beta Inc');
});

it('shows a single customer', function () {
    $this->actingAs($this->manager)
        ->getJson("/api/v1/ar/customers/{$this->customer->ulid}")
        ->assertOk()
        ->assertJsonPath('data.company_name', 'Acme Corp');
});

it('lists customer invoices', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/ar/invoices')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a customer invoice', function () {
    $this->actingAs($this->manager)
        ->postJson('/api/v1/ar/invoices', [
            'customer_id'    => $this->customer->id,
            'invoice_number' => 'CI-001',
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'total_amount'   => 75000.00,
        ])
        ->assertCreated()
        ->assertJsonPath('data.invoice_number', 'CI-001');
});

it('returns AR aging report', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/ar/aging-report')
        ->assertOk()
        ->assertJsonStructure(['data', 'as_of_date']);
});
