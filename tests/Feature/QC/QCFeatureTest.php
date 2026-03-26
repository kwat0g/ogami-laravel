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
uses()->group('feature', 'qc');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);
    $this->seed(DepartmentPositionSeeder::class);
    $this->seed(DepartmentModuleAssignmentSeeder::class);

    $qcDept = Department::where('code', 'QC')->first();
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
    $this->manager->departments()->attach($qcDept->id, ['is_primary' => true]);
});

it('lists inspections', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/qc/inspections')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists NCRs', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/qc/ncrs')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists inspection templates', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/qc/templates')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
