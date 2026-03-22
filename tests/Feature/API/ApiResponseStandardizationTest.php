<?php

declare(strict_types=1);

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ModuleSeeder::class);
    $this->seed(\Database\Seeders\ModulePermissionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentPositionSeeder::class);
    $this->seed(\Database\Seeders\DepartmentModuleAssignmentSeeder::class);
    
    $this->user = \App\Models\User::factory()->create();
    $this->actingAs($this->user);
});

// ── Fixed Assets API Standardization Tests ─────────────────────────────────

describe('Fixed Assets API Response Format', function () {
    beforeEach(function () {
        $acctgDept = \App\Domains\HR\Models\Department::where('code', 'ACCTG')->first();
        $this->user->assignRole('manager');
        $this->user->departments()->attach($acctgDept->id, ['is_primary' => true]);
    });

    it('returns standardized category list response', function () {
        \App\Domains\FixedAssets\Models\FixedAssetCategory::factory()->count(3)->create([
            'created_by_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/fixed-assets/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ulid',
                        'name',
                        'code_prefix',
                        'default_depreciation_method',
                        'default_useful_life_years',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    });

    it('returns standardized asset list response with pagination', function () {
        $category = \App\Domains\FixedAssets\Models\FixedAssetCategory::factory()->create([
            'created_by_id' => $this->user->id,
        ]);
        \App\Domains\FixedAssets\Models\FixedAsset::factory()->count(5)->create([
            'category_id' => $category->id,
            'created_by_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/fixed-assets');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ulid',
                        'asset_code',
                        'description',
                        'category_id',
                        'department_id',
                        'acquisition_date',
                        'acquisition_cost_centavos',
                        'status',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
            ]);
    });

    it('returns standardized single asset response', function () {
        $category = \App\Domains\FixedAssets\Models\FixedAssetCategory::factory()->create([
            'created_by_id' => $this->user->id,
        ]);
        $asset = \App\Domains\FixedAssets\Models\FixedAsset::factory()->create([
            'category_id' => $category->id,
            'created_by_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/fixed-assets/{$asset->ulid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'ulid',
                    'asset_code',
                    'description',
                    'category',
                    'department',
                    'created_at',
                    'updated_at',
                ],
            ]);
    });
});

// ── Inventory API Standardization Tests ────────────────────────────────────

describe('Inventory API Response Format', function () {
    beforeEach(function () {
        $whDept = \App\Domains\HR\Models\Department::where('code', 'WH')->first();
        $this->user->assignRole('manager');
        $this->user->departments()->attach($whDept->id, ['is_primary' => true]);
    });

    it('returns standardized category list response', function () {
        \App\Domains\Inventory\Models\ItemCategory::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/inventory/items/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'description',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    });

    it('returns standardized item list response with pagination', function () {
        \App\Domains\Inventory\Models\ItemMaster::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/inventory/items');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ulid',
                        'item_code',
                        'name',
                        'type',
                        'category',
                        'is_active',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    });
});

// ── Maintenance API Standardization Tests ──────────────────────────────────

describe('Maintenance API Response Format', function () {
    beforeEach(function () {
        $maintDept = \App\Domains\HR\Models\Department::where('code', 'MAINT')->first();
        $this->user->assignRole('manager');
        $this->user->departments()->attach($maintDept->id, ['is_primary' => true]);
    });

    it('returns standardized equipment list response', function () {
        \App\Domains\Maintenance\Models\Equipment::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/maintenance/equipment');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ulid',
                        'equipment_code',
                        'name',
                        'category',
                        'status',
                        'is_active',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    });

    it('returns standardized work order parts response', function () {
        $category = \App\Domains\Inventory\Models\ItemCategory::factory()->create();
        $item = \App\Domains\Inventory\Models\ItemMaster::factory()->create(['category_id' => $category->id]);
        $location = \App\Domains\Inventory\Models\WarehouseLocation::create([
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
            'is_active' => true,
        ]);
        $wo = \App\Domains\Maintenance\Models\MaintenanceWorkOrder::factory()->create();
        $wo->spareParts()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => 5,
            'unit_cost_centavos' => 10000,
            'total_cost_centavos' => 50000,
            'added_by_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/maintenance/work-orders/{$wo->ulid}/parts");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'maintenance_work_order_id',
                        'item_id',
                        'item',
                        'location_id',
                        'location',
                        'quantity',
                        'unit_cost_centavos',
                        'unit_cost',
                        'total_cost_centavos',
                        'total_cost',
                    ],
                ],
            ]);
    });
});

// ── General API Standards Tests ────────────────────────────────────────────

describe('General API Standards', function () {
    beforeEach(function () {
        $acctgDept = \App\Domains\HR\Models\Department::where('code', 'ACCTG')->first();
        $this->user->assignRole('manager');
        $this->user->departments()->attach($acctgDept->id, ['is_primary' => true]);
    });

    it('always includes data wrapper for list endpoints', function () {
        $category = \App\Domains\FixedAssets\Models\FixedAssetCategory::factory()->create([
            'created_by_id' => $this->user->id,
        ]);
        \App\Domains\FixedAssets\Models\FixedAsset::factory()->count(3)->create([
            'category_id' => $category->id,
            'created_by_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/fixed-assets');

        $response->assertOk();
        $json = $response->json();

        // Must have 'data' key at root
        expect($json)->toHaveKey('data');
        // Must have 'meta' for pagination
        expect($json)->toHaveKey('meta');
        // Data must be an array
        expect($json['data'])->toBeArray();
    });

    it('always includes data wrapper for single resource endpoints', function () {
        $category = \App\Domains\FixedAssets\Models\FixedAssetCategory::factory()->create([
            'created_by_id' => $this->user->id,
        ]);
        $asset = \App\Domains\FixedAssets\Models\FixedAsset::factory()->create([
            'category_id' => $category->id,
            'created_by_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/fixed-assets/{$asset->ulid}");

        $response->assertOk();
        $json = $response->json();

        // Must have 'data' key at root
        expect($json)->toHaveKey('data');
        // Data must be an object (associative array)
        expect($json['data'])->toBeArray();
        expect($json['data'])->toHaveKey('id');
    });

    it('includes ULID in all resource responses', function () {
        $category = \App\Domains\FixedAssets\Models\FixedAssetCategory::factory()->create([
            'created_by_id' => $this->user->id,
        ]);
        $asset = \App\Domains\FixedAssets\Models\FixedAsset::factory()->create([
            'category_id' => $category->id,
            'created_by_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/fixed-assets/{$asset->ulid}");

        $response->assertOk();
        $json = $response->json();

        expect($json['data'])->toHaveKey('ulid');
        expect($json['data']['ulid'])->toBe($asset->ulid);
    });

    it('returns centavos and decimal amounts for monetary values', function () {
        $category = \App\Domains\FixedAssets\Models\FixedAssetCategory::factory()->create([
            'created_by_id' => $this->user->id,
        ]);
        $asset = \App\Domains\FixedAssets\Models\FixedAsset::factory()->create([
            'category_id' => $category->id,
            'created_by_id' => $this->user->id,
            'acquisition_cost_centavos' => 500000, // ₱5,000.00
            'residual_value_centavos' => 50000, // ₱500.00
        ]);

        $response = $this->getJson("/api/v1/fixed-assets/{$asset->ulid}");

        $response->assertOk();
        $json = $response->json();

        expect($json['data'])->toHaveKey('acquisition_cost_centavos');
        expect($json['data'])->toHaveKey('acquisition_cost');
        expect($json['data']['acquisition_cost_centavos'])->toBe(500000);
        expect($json['data']['acquisition_cost'])->toBe(5000);
    });
});
