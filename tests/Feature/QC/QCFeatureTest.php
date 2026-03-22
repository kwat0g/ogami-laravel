<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'qc');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ModuleSeeder::class);
    $this->seed(\Database\Seeders\ModulePermissionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentPositionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentModuleAssignmentSeeder::class);

    $qcDept = \App\Domains\HR\Models\Department::where('code', 'QC')->first();
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
