<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'delivery');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->deliveryUser = User::factory()->create();
    $this->deliveryUser->assignRole('admin');
    $this->deliveryUser->givePermissionTo('delivery.view');
});

it('lists delivery receipts', function () {
    $this->actingAs($this->deliveryUser)
        ->getJson('/api/v1/delivery/receipts')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists shipments', function () {
    $this->actingAs($this->deliveryUser)
        ->getJson('/api/v1/delivery/shipments')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists delivery routes', function () {
    $this->actingAs($this->deliveryUser)
        ->getJson('/api/v1/delivery/routes')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('rejects unauthenticated access to delivery endpoints', function () {
    $this->getJson('/api/v1/delivery/receipts')
        ->assertUnauthorized();
});
