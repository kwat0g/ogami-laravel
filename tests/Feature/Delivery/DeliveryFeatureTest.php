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
uses()->group('feature', 'delivery');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);
    $this->seed(DepartmentPositionSeeder::class);
    $this->seed(DepartmentModuleAssignmentSeeder::class);

    $whDept = Department::where('code', 'WH')->first();
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
    $this->manager->departments()->attach($whDept->id, ['is_primary' => true]);
});

it('lists shipments', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/delivery/shipments')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists delivery receipts', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/delivery/receipts')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
