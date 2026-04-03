<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Services\ClientOrderService;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);

    $this->salesUser = User::factory()->create();
    $this->salesUser->assignRole('head');

    $this->customer = Customer::factory()->unlimitedCredit()->create();
    $this->clientUser = User::factory()->create(['client_id' => $this->customer->id]);
    $this->clientUser->assignRole('client');

    $category = ItemCategory::create([
        'code' => 'FG-FORCE',
        'name' => 'Finished Goods Force',
        'is_active' => true,
    ]);

    $this->item = ItemMaster::create([
        'item_code' => 'FG-FORCE-001',
        'name' => 'Forced Product',
        'unit_of_measure' => 'pcs',
        'category_id' => $category->id,
        'type' => 'finished_good',
        'is_active' => true,
        'standard_price_centavos' => 150_00,
    ]);

    BillOfMaterials::create([
        'product_item_id' => $this->item->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_production_days' => 4,
        'standard_cost_centavos' => 0,
    ]);

    $warehouse = WarehouseLocation::create([
        'name' => 'FG Warehouse',
        'code' => 'WH-FG',
        'is_active' => true,
    ]);

    StockBalance::create([
        'item_id' => $this->item->id,
        'location_id' => $warehouse->id,
        'quantity_on_hand' => 100,
        'quantity_reserved' => 0,
    ]);

    $this->service = app(ClientOrderService::class);
});

it('creates force_production orders for approved client order even when stock is sufficient', function (): void {
    $order = $this->service->submitOrder(
        customerId: $this->customer->id,
        items: [[
            'item_master_id' => $this->item->id,
            'quantity' => 20,
            'unit_price_centavos' => 150_00,
        ]],
        requestedDate: now()->addDays(10)->toDateString(),
        submittedByUserId: $this->clientUser->id,
    );

    $approved = $this->service->approveOrder($order, $this->salesUser->id);

    $result = $this->service->forceProductionFromOrder(
        order: $approved,
        userId: $this->salesUser->id,
        mode: 'preserve_stock_produce_full',
        reason: 'Build production buffer for upcoming forecast',
    );

    $forcePo = ProductionOrder::query()
        ->where('client_order_id', $order->id)
        ->where('product_item_id', $this->item->id)
        ->where('source_type', 'force_production')
        ->first();

    expect($forcePo)->not->toBeNull()
        ->and((float) $forcePo->qty_required)->toBe(20.0)
        ->and($forcePo->status)->toBe('draft')
        ->and($forcePo->requires_release_approval)->toBeTrue()
        ->and($result->status)->toBe('in_production');
});
