<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Domains\Maintenance\Models\Equipment;
use App\Models\User;
use Database\Seeders\DepartmentModuleAssignmentSeeder;
use Database\Seeders\DepartmentPositionSeeder;
use Database\Seeders\ModulePermissionSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'maintenance');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);
    $this->seed(DepartmentPositionSeeder::class);
    $this->seed(DepartmentModuleAssignmentSeeder::class);

    $maintDept = Department::where('code', 'MAINT')->first();
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
    $this->manager->departments()->attach($maintDept->id, ['is_primary' => true]);
});

it('lists equipment', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/maintenance/equipment')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates equipment', function () {
    $this->actingAs($this->manager)
        ->postJson('/api/v1/maintenance/equipment', [
            'name' => 'CNC Machine 01',
            'asset_tag' => 'EQ-001',
            'type' => 'machine',
            'location' => 'Plant Floor',
            'status' => 'operational',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'CNC Machine 01');
});

it('lists work orders', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/maintenance/work-orders')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a work order', function () {
    $equipment = Equipment::create([
        'name' => 'Test Machine',
        'asset_tag' => 'EQ-TEST',
        'type' => 'machine',
        'status' => 'operational',
    ]);

    $this->actingAs($this->manager)
        ->postJson('/api/v1/maintenance/work-orders', [
            'title' => 'Oil change',
            'type' => 'preventive',
            'priority' => 'normal',
            'equipment_id' => $equipment->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Oil change');
});
