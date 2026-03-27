<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'crm');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
});

it('lists client orders', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/crm/orders')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists support tickets', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/crm/tickets')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('rejects unauthenticated access to CRM endpoints', function () {
    $this->getJson('/api/v1/crm/orders')
        ->assertUnauthorized();
});
