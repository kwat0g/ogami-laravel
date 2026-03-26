<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Models\User;
use Database\Seeders\DepartmentModuleAssignmentSeeder;
use Database\Seeders\DepartmentPositionSeeder;
use Database\Seeders\ModulePermissionSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'crm');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);
    $this->seed(DepartmentPositionSeeder::class);
    $this->seed(DepartmentModuleAssignmentSeeder::class);

    // CRM agents are in the Sales department (module_access:crm is assigned to SALES)
    $opsDept = Department::where('code', 'SALES')->first();
    $this->agent = User::factory()->create();
    $this->agent->assignRole('officer');
    $this->agent->departments()->attach($opsDept->id, ['is_primary' => true]);

    $this->client = User::factory()->create();
    $this->client->assignRole('client');
});

it('lists tickets', function () {
    $this->actingAs($this->agent)
        ->getJson('/api/v1/crm/tickets')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a ticket', function () {
    $this->actingAs($this->agent)
        ->postJson('/api/v1/crm/tickets', [
            'subject' => 'Test Ticket',
            'description' => 'Something is broken and needs fixing.',
            'priority' => 'high',
            'type' => 'request',
        ])
        ->assertCreated()
        ->assertJsonPath('data.subject', 'Test Ticket');
});

it('returns CRM dashboard metrics', function () {
    $this->actingAs($this->agent)
        ->getJson('/api/v1/crm/dashboard')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'open_tickets',
            'in_progress_tickets',
            'sla_compliance_pct',
        ]]);
});
