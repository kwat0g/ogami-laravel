<?php

declare(strict_types=1);

use App\Domains\Mold\Models\MoldMaster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'mold');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
});

it('lists molds', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/mold')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a mold', function () {
    $this->actingAs($this->manager)
        ->postJson('/api/v1/mold', [
            'mold_code'       => 'MLD-001',
            'name'            => 'Test Mold',
            'type'            => 'injection',
            'max_shot_count'  => 100000,
            'current_shot_count' => 0,
            'status'          => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.mold_code', 'MLD-001');
});
