<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'delivery');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ModuleSeeder::class);
    $this->seed(\Database\Seeders\ModulePermissionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentPositionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentModuleAssignmentSeeder::class);

    $whDept = \App\Domains\HR\Models\Department::where('code', 'WH')->first();
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
