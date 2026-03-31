<?php

declare(strict_types=1);

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Production\Models\BillOfMaterials;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('returns smart defaults with query-string product_item_id', function (): void {
    $product = ItemMaster::factory()->create([
        'type' => 'finished_good',
    ]);

    $bom = BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_production_days' => 3,
        'standard_cost_centavos' => 0,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/production/orders/smart-defaults?product_item_id='.$product->id)
        ->assertOk()
        ->assertJsonPath('data.suggested_bom_id', $bom->id)
        ->assertJsonPath('data.calculated_end_date', null);
});

it('calculates smart-defaults end date when target_start_date is provided', function (): void {
    $product = ItemMaster::factory()->create([
        'type' => 'finished_good',
    ]);

    BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_production_days' => 5,
        'standard_cost_centavos' => 0,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/production/orders/smart-defaults?product_item_id='.$product->id.'&target_start_date=2026-04-01')
        ->assertOk()
        ->assertJsonPath('data.calculated_end_date', '2026-04-05');
});
