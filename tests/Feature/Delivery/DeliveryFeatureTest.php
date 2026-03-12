<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'delivery');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
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
