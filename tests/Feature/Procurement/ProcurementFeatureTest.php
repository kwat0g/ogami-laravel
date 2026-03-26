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
uses()->group('feature', 'procurement');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);
    $this->seed(DepartmentPositionSeeder::class);
    $this->seed(DepartmentModuleAssignmentSeeder::class);

    $purchDept = Department::where('code', 'PURCH')->first();
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
    $this->manager->departments()->attach($purchDept->id, ['is_primary' => true]);
});

it('lists purchase requests', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/procurement/purchase-requests')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists purchase orders', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/procurement/purchase-orders')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists goods receipts', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/procurement/goods-receipts')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
