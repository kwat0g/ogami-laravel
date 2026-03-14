<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'procurement');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('purchasing_officer');
});

it('lists purchase requests', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/procurement/purchase-requests')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists purchase orders', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/procurement/purchase-orders')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists goods receipts', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/procurement/goods-receipts')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
