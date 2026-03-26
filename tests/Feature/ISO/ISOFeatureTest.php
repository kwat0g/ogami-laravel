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
uses()->group('feature', 'iso');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);
    $this->seed(DepartmentPositionSeeder::class);
    $this->seed(DepartmentModuleAssignmentSeeder::class);

    $isoDept = Department::where('code', 'ISO')->first();
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
    $this->manager->departments()->attach($isoDept->id, ['is_primary' => true]);
});

it('lists controlled documents', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/iso/documents')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists internal audits', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/iso/audits')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
