<?php

declare(strict_types=1);

use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'inventory');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');

    $this->category = ItemCategory::create([
        'code'      => 'RM',
        'name'      => 'Raw Materials',
        'is_active' => true,
    ]);
});

it('lists item masters', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/inventory/items')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates an item master', function () {
    $this->actingAs($this->manager)
        ->postJson('/api/v1/inventory/items', [
            'item_code'       => 'RM-TEST-001',
            'name'            => 'Test Material',
            'category_id'     => $this->category->id,
            'type'            => 'raw_material',
            'unit_of_measure' => 'kg',
            'is_active'       => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.item_code', 'RM-TEST-001');
});

it('lists warehouse locations', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/inventory/locations')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists stock ledger entries', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/inventory/stock')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
