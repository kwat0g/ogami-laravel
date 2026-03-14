<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'qc');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('qc_manager');
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
