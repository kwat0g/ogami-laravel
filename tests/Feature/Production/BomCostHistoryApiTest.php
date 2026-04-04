<?php

declare(strict_types=1);

use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Services\BomService;
use App\Models\User;

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);

    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('admin');

    $category = ItemCategory::firstOrCreate(
        ['code' => 'RAW-BOM-HIST'],
        ['name' => 'Raw Material BOM History', 'is_active' => true]
    );

    $this->rawMaterial = ItemMaster::factory()->create([
        'category_id' => $category->id,
        'type' => 'raw_material',
        'standard_price_centavos' => 1_000,
    ]);

    $this->finishedGood = ItemMaster::factory()->create([
        'category_id' => $category->id,
        'type' => 'finished_good',
        'standard_price_centavos' => 0,
    ]);

    $this->bom = BillOfMaterials::create([
        'product_item_id' => $this->finishedGood->id,
        'version' => '1.0',
        'is_active' => true,
    ]);

    BomComponent::create([
        'bom_id' => $this->bom->id,
        'component_item_id' => $this->rawMaterial->id,
        'qty_per_unit' => 2,
        'unit_of_measure' => 'kg',
        'scrap_factor_pct' => 0,
    ]);
});

it('returns paginated BOM cost history in latest-first order', function (): void {
    $service = app(BomService::class);

    $service->rollupCost($this->bom->fresh('components.componentItem')); // 2 * 1,000 = 2,000

    $this->rawMaterial->update(['standard_price_centavos' => 1_300]);
    $service->rollupCost($this->bom->fresh('components.componentItem')); // 2 * 1,300 = 2,600

    $pageOne = $this->actingAs($this->viewer)
        ->getJson("/api/v1/production/boms/{$this->bom->ulid}/cost-history?per_page=1")
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(1, 'data');

    expect($pageOne->json('data.0.material_cost_centavos'))->toBe(2600);
    expect($pageOne->json('data.0.source'))->toBe('rollup');

    $pageTwo = $this->actingAs($this->viewer)
        ->getJson("/api/v1/production/boms/{$this->bom->ulid}/cost-history?per_page=1&page=2")
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(1, 'data');

    expect($pageTwo->json('data.0.material_cost_centavos'))->toBe(2000);
});

it('validates cost history pagination inputs', function (): void {
    $this->actingAs($this->viewer)
        ->getJson("/api/v1/production/boms/{$this->bom->ulid}/cost-history?per_page=101")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['per_page']);
});

it('requires authentication to access BOM cost history endpoint', function (): void {
    $this->getJson("/api/v1/production/boms/{$this->bom->ulid}/cost-history")
        ->assertUnauthorized();
});

it('returns expected cost history schema with default pagination', function (): void {
    $service = app(BomService::class);

    $service->rollupCost($this->bom->fresh('components.componentItem'));

    $this->actingAs($this->viewer)
        ->getJson("/api/v1/production/boms/{$this->bom->ulid}/cost-history")
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonStructure([
            'data' => [[
                'id',
                'ulid',
                'bom_id',
                'bom_version',
                'material_cost_centavos',
                'component_lines',
                'source',
                'created_by_id',
                'created_at',
                'updated_at',
            ]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});
