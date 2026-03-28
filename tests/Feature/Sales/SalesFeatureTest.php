<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Sales\Models\Quotation;
use App\Domains\Sales\Models\QuotationItem;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Services\ProfitMarginService;
use App\Domains\Sales\Services\QuotationService;
use App\Domains\Sales\Services\SalesOrderService;
use App\Models\User;

uses()->group('feature', 'sales');

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
});

// ── Quotation Lifecycle ──────────────────────────────────────────────────────

it('creates a quotation with line items', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    $customer = Customer::factory()->create();
    $item = ItemMaster::factory()->create(['standard_price_centavos' => 10000]);

    $service = app(QuotationService::class);
    $quotation = $service->store([
        'customer_id' => $customer->id,
        'validity_date' => now()->addDays(30)->toDateString(),
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 5,
                'unit_price_centavos' => 15000,
            ],
        ],
    ], $user);

    expect($quotation)->toBeInstanceOf(Quotation::class);
    expect($quotation->status)->toBe('draft');
    expect($quotation->total_centavos)->toBe(75000); // 5 * 15000
    expect($quotation->items)->toHaveCount(1);
});

it('transitions quotation through draft -> sent -> accepted', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    $customer = Customer::factory()->create();
    $item = ItemMaster::factory()->create();

    $service = app(QuotationService::class);
    $quotation = $service->store([
        'customer_id' => $customer->id,
        'validity_date' => now()->addDays(30)->toDateString(),
        'items' => [
            ['item_id' => $item->id, 'quantity' => 1, 'unit_price_centavos' => 10000],
        ],
    ], $user);

    expect($quotation->status)->toBe('draft');

    $quotation = $service->send($quotation);
    expect($quotation->status)->toBe('sent');

    $quotation = $service->accept($quotation);
    expect($quotation->status)->toBe('accepted');
});

it('rejects sending a non-draft quotation', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    $customer = Customer::factory()->create();
    $item = ItemMaster::factory()->create();

    $service = app(QuotationService::class);
    $quotation = $service->store([
        'customer_id' => $customer->id,
        'validity_date' => now()->addDays(30)->toDateString(),
        'items' => [
            ['item_id' => $item->id, 'quantity' => 1, 'unit_price_centavos' => 10000],
        ],
    ], $user);

    $quotation = $service->send($quotation);

    expect(fn () => $service->send($quotation))
        ->toThrow(\App\Shared\Exceptions\DomainException::class);
});

// ── Sales Order SoD ──────────────────────────────────────────────────────────

it('blocks sales order self-confirmation (SoD)', function () {
    $creator = User::factory()->create();
    $creator->assignRole('manager');

    $customer = Customer::factory()->create();
    $item = ItemMaster::factory()->create();

    $service = app(SalesOrderService::class);
    $order = $service->store([
        'customer_id' => $customer->id,
        'items' => [
            ['item_id' => $item->id, 'quantity' => 1, 'unit_price_centavos' => 10000],
        ],
    ], $creator);

    // Same user who created tries to confirm -- should fail
    expect(fn () => $service->confirm($order, $creator))
        ->toThrow(\App\Shared\Exceptions\DomainException::class, 'Separation of Duties');
});

it('allows sales order confirmation by a different user', function () {
    $creator = User::factory()->create();
    $creator->assignRole('manager');
    $approver = User::factory()->create();
    $approver->assignRole('manager');

    $customer = Customer::factory()->create();
    $item = ItemMaster::factory()->create();

    $service = app(SalesOrderService::class);
    $order = $service->store([
        'customer_id' => $customer->id,
        'items' => [
            ['item_id' => $item->id, 'quantity' => 1, 'unit_price_centavos' => 10000],
        ],
    ], $creator);

    $confirmed = $service->confirm($order, $approver);
    expect($confirmed->status)->toBe('confirmed');
});

// ── Profit Margin ────────────────────────────────────────────────────────────

it('calculates quotation margin with BOM cost data', function () {
    $user = User::factory()->create();

    $product = ItemMaster::factory()->create(['type' => 'finished_good', 'standard_price_centavos' => 0]);

    BillOfMaterials::create([
        'product_item_id' => $product->id,
        'version' => '1.0',
        'is_active' => true,
        'standard_cost_centavos' => 50000,
    ]);

    $customer = Customer::factory()->create();

    $quotation = Quotation::create([
        'quotation_number' => 'QT-TEST-MARGIN',
        'customer_id' => $customer->id,
        'validity_date' => now()->addDays(30),
        'total_centavos' => 100000,
        'status' => 'draft',
        'created_by_id' => $user->id,
    ]);
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'item_id' => $product->id,
        'quantity' => 1,
        'unit_price_centavos' => 100000,
        'line_total_centavos' => 100000,
    ]);

    $service = app(ProfitMarginService::class);
    $result = $service->quotationMargin($quotation);

    expect($result['overall_margin_pct'])->toBe(50.0);
    expect($result['lines'][0]['below_cost'])->toBeFalse();
});
