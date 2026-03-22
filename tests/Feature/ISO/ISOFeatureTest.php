<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'iso');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ModuleSeeder::class);
    $this->seed(\Database\Seeders\ModulePermissionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentPositionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentModuleAssignmentSeeder::class);

    $isoDept = \App\Domains\HR\Models\Department::where('code', 'ISO')->first();
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
