<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Services\ClientOrderService;
use App\Domains\Inventory\Models\ItemMaster;
use App\Models\User;
use App\Shared\Exceptions\DomainException;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
uses()->group('feature', 'crm', 'client-orders');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Sales agent (approver)
    $this->salesUser = User::factory()->create();
    $this->salesUser->assignRole('officer');
    $this->salesUser->givePermissionTo(['sales.order_review', 'sales.order_approve', 'sales.order_reject', 'sales.order_negotiate']);

    // Customer + linked client portal user
    $this->customer = Customer::factory()->withCreditLimit(50_000)->create();
    $this->clientUser = User::factory()->create(['client_id' => $this->customer->id]);
    $this->clientUser->assignRole('client');

    // A different sales user who will be the submitter (for SoD test)
    $this->submitterUser = User::factory()->create();
    $this->submitterUser->assignRole('officer');
    $this->submitterUser->givePermissionTo(['sales.order_review', 'sales.order_approve', 'sales.order_reject']);

    // Finished goods item with a catalog price
    $this->item = ItemMaster::factory()->create([
        'type' => 'finished_goods',
        'is_active' => true,
        'standard_price_centavos' => 100_00, // ₱100.00
    ]);

    $this->service = app(ClientOrderService::class);
});

// ── Credit Limit ─────────────────────────────────────────────────────────────

it('blocks order submission when customer credit limit would be exceeded', function () {
    // Customer has ₱50,000 credit limit; order is ₱60,000 (600 units × ₱100)
    expect(fn () => $this->service->submitOrder(
        customerId: $this->customer->id,
        items: [[
            'item_master_id' => $this->item->id,
            'quantity' => 600,
            'unit_price_centavos' => 100_00,
        ]],
        submittedByUserId: $this->clientUser->id
    ))->toThrow(DomainException::class);
});

it('allows order submission within credit limit', function () {
    $order = $this->service->submitOrder(
        customerId: $this->customer->id,
        items: [[
            'item_master_id' => $this->item->id,
            'quantity' => 10, // ₱1,000 total — well within ₱50,000 limit
            'unit_price_centavos' => 100_00,
        ]],
        submittedByUserId: $this->clientUser->id
    );

    expect($order->status)->toBe(ClientOrder::STATUS_PENDING);
});

it('blocks order submission for inactive customer', function () {
    $inactive = Customer::factory()->inactive()->create();
    $inactiveClient = User::factory()->create(['client_id' => $inactive->id]);
    $inactiveClient->assignRole('client');

    expect(fn () => $this->service->submitOrder(
        customerId: $inactive->id,
        items: [['item_master_id' => $this->item->id, 'quantity' => 1, 'unit_price_centavos' => 100_00]],
        submittedByUserId: $inactiveClient->id
    ))->toThrow(DomainException::class, 'inactive');
});

// ── Price Validation ──────────────────────────────────────────────────────────

it('blocks order when item price is below standard price', function () {
    expect(fn () => $this->service->submitOrder(
        customerId: $this->customer->id,
        items: [[
            'item_master_id' => $this->item->id,
            'quantity' => 1,
            'unit_price_centavos' => 50_00, // ₱50 — below ₱100 standard
        ]],
        submittedByUserId: $this->clientUser->id
    ))->toThrow(DomainException::class, 'standard price');
});

it('allows order when item price equals standard price', function () {
    $order = $this->service->submitOrder(
        customerId: $this->customer->id,
        items: [[
            'item_master_id' => $this->item->id,
            'quantity' => 1,
            'unit_price_centavos' => 100_00, // exactly ₱100
        ]],
        submittedByUserId: $this->clientUser->id
    );

    expect($order->status)->toBe(ClientOrder::STATUS_PENDING);
});

it('allows order for items without a standard price', function () {
    $item = ItemMaster::factory()->create([
        'type' => 'finished_goods',
        'is_active' => true,
        'standard_price_centavos' => null,
    ]);

    $order = $this->service->submitOrder(
        customerId: $this->customer->id,
        items: [['item_master_id' => $item->id, 'quantity' => 1, 'unit_price_centavos' => 1_00]],
        submittedByUserId: $this->clientUser->id
    );

    expect($order->status)->toBe(ClientOrder::STATUS_PENDING);
});

// ── Segregation of Duties ─────────────────────────────────────────────────────

it('blocks approval by the same user who submitted the order (SOD-CLIENT-001)', function () {
    $order = $this->service->submitOrder(
        customerId: $this->customer->id,
        items: [['item_master_id' => $this->item->id, 'quantity' => 1, 'unit_price_centavos' => 100_00]],
        submittedByUserId: $this->submitterUser->id
    );

    expect(fn () => $this->service->approveOrder($order, $this->submitterUser->id))
        ->toThrow(DomainException::class, 'SOD-CLIENT-001');
});

it('allows approval by a different user than the submitter', function () {
    // Use unlimited credit customer so the order can be approved
    $unlimitedCustomer = Customer::factory()->unlimitedCredit()->create();
    $clientUser = User::factory()->create(['client_id' => $unlimitedCustomer->id]);
    $clientUser->assignRole('client');

    $order = $this->service->submitOrder(
        customerId: $unlimitedCustomer->id,
        items: [['item_master_id' => $this->item->id, 'quantity' => 1, 'unit_price_centavos' => 100_00]],
        submittedByUserId: $this->submitterUser->id
    );

    // salesUser (different from submitterUser) can approve
    expect(fn () => $this->service->approveOrder($order, $this->salesUser->id))
        ->not->toThrow(DomainException::class);
});

// ── Turn Enforcement ──────────────────────────────────────────────────────────

it('blocks client from responding when order is already client_responded', function () {
    // Create via service then force to client_responded
    $order = $this->service->submitOrder(
        customerId: $this->customer->id,
        items: [['item_master_id' => $this->item->id, 'quantity' => 1, 'unit_price_centavos' => 100_00]],
        submittedByUserId: $this->clientUser->id
    );
    $order->update(['status' => ClientOrder::STATUS_CLIENT_RESPONDED]);

    expect(fn () => $this->service->clientRespond($order->fresh(), 'accept', null, $this->clientUser->id))
        ->toThrow(DomainException::class, 'client response');
});

// ── Reject from client_responded ─────────────────────────────────────────────

it('allows rejection when order is in client_responded status', function () {
    // Create via service then force to client_responded
    $order = $this->service->submitOrder(
        customerId: $this->customer->id,
        items: [['item_master_id' => $this->item->id, 'quantity' => 1, 'unit_price_centavos' => 100_00]],
        submittedByUserId: $this->clientUser->id
    );
    $order->update(['status' => ClientOrder::STATUS_CLIENT_RESPONDED]);

    $rejected = $this->service->rejectOrder($order->fresh(), 'price_issue', $this->salesUser->id);

    expect($rejected->status)->toBe(ClientOrder::STATUS_REJECTED);
});

// ── VP Escalation ─────────────────────────────────────────────────────────────

it('escalates high-value orders to vp_pending instead of approving directly', function () {
    // Set threshold to ₱1,000 (100,000 centavos) — easy to exceed in test
    DB::table('system_settings')
        ->updateOrInsert(
            ['key' => 'client_order_vp_threshold_centavos'],
            ['value' => '100000', 'data_type' => 'integer', 'group' => 'sales',
                'label' => 'Test', 'editable_by_role' => 'admin', 'is_sensitive' => false,
                'created_at' => now(), 'updated_at' => now()]
        );

    $unlimitedCustomer = Customer::factory()->unlimitedCredit()->create();
    $clientUser = User::factory()->create(['client_id' => $unlimitedCustomer->id]);
    $clientUser->assignRole('client');

    // Order total = 100 × ₱100 = ₱10,000 = 1,000,000 centavos > threshold
    $order = $this->service->submitOrder(
        customerId: $unlimitedCustomer->id,
        items: [['item_master_id' => $this->item->id, 'quantity' => 100, 'unit_price_centavos' => 100_00]],
        submittedByUserId: $this->submitterUser->id
    );

    $escalated = $this->service->approveOrder($order, $this->salesUser->id);

    expect($escalated->status)->toBe(ClientOrder::STATUS_VP_PENDING);
});
