<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'fixed-assets');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('officer');
});

it('lists asset categories', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/fixed-assets/categories')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates an asset category', function () {
    $this->actingAs($this->manager)
        ->postJson('/api/v1/fixed-assets/categories', [
            'code'              => 'FURN',
            'name'              => 'Furniture',
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'FURN');
});

it('lists fixed assets', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/fixed-assets')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
