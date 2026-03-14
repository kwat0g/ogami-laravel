<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'budget');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('officer');
});

it('lists cost centers', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/budget/cost-centers')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a cost center', function () {
    $this->actingAs($this->manager)
        ->postJson('/api/v1/budget/cost-centers', [
            'code'      => 'CC-PROD',
            'name'      => 'Production Center',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'CC-PROD');
});

it('lists budget lines', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/budget')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('returns budget variance report', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/budget/variance?fiscal_year=2026')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
