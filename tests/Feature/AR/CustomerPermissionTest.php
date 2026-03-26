<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Domains\HR\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ModuleSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ModulePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'DepartmentPositionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'DepartmentModuleAssignmentSeeder'])->assertExitCode(0);
});

test('purchasing officer can create customer with customers.manage permission', function (): void {
    $acctgDept = Department::where('code', 'ACCTG')->first();

    $officer = User::factory()->create();
    $officer->assignRole('officer');
    $officer->departments()->attach($acctgDept->id, ['is_primary' => true]);

    $this->actingAs($officer)
        ->postJson('/api/v1/ar/customers', [
            'name' => 'Beta Inc',
            'email' => 'billing@beta.test',
            'credit_limit' => 150000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Beta Inc');

    $this->assertDatabaseHas('customers', [
        'name' => 'Beta Inc',
        'created_by' => $officer->id,
    ]);
});

test('purchasing officer can update active customer with customers.manage permission', function (): void {
    $acctgDept = Department::where('code', 'ACCTG')->first();

    $officer = User::factory()->create();
    $officer->assignRole('officer');
    $officer->departments()->attach($acctgDept->id, ['is_primary' => true]);

    $customer = Customer::create([
        'name' => 'Acme Industries',
        'is_active' => true,
        'created_by' => $officer->id,
    ]);

    $this->actingAs($officer)
        ->putJson("/api/v1/ar/customers/{$customer->ulid}", [
            'name' => 'Acme Industries Updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme Industries Updated');
});
