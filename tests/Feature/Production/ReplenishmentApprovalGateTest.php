<?php

declare(strict_types=1);

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Services\ProductionOrderService;
use App\Models\User;
use App\Shared\Exceptions\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);

    $this->creator = User::factory()->create();
    $this->creator->assignRole('manager');

    $this->approver = User::factory()->create();
    $this->approver->assignRole('manager');

    $this->item = ItemMaster::factory()->create([
        'type' => 'finished_good',
        'min_batch_size' => 25,
    ]);

    BillOfMaterials::create([
        'product_item_id' => $this->item->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_production_days' => 3,
        'standard_cost_centavos' => 0,
    ]);

    $this->service = app(ProductionOrderService::class);
});

it('blocks release for replenishment orders until explicit approval is recorded', function (): void {
    $order = $this->service->createReplenishmentOrder([
        'product_item_id' => $this->item->id,
        'target_stock_level' => 80,
        'notes' => 'Prepare stocks before next client demand wave',
    ], $this->creator);

    expect(fn () => $this->service->release($order))
        ->toThrow(DomainException::class, 'requires prior approval');

    $approved = $this->service->approveRelease($order, $this->approver, 'Approved for replenishment run');

    expect($approved->approved_for_release_by)->toBe($this->approver->id)
        ->and($approved->approved_for_release_at)->not->toBeNull();

    $released = $this->service->release($approved);

    expect($released->status)->toBe('released');
});

it('enforces minimum batch rounding for replenishment quantity', function (): void {
    $order = $this->service->createReplenishmentOrder([
        'product_item_id' => $this->item->id,
        'target_stock_level' => 10,
    ], $this->creator);

    expect((float) $order->qty_required)->toBe(25.0)
        ->and($order->source_type)->toBe('replenishment')
        ->and($order->requires_release_approval)->toBeTrue();
});
