<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Domains\Sales\Models\Quotation;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
});

// ── Unauthenticated access ───────────────────────────────────────────────────

it('returns 401 for unauthenticated access to quotation list', function () {
    $this->getJson('/api/v1/sales/quotations')
        ->assertStatus(401);
});

it('returns 401 for unauthenticated access to sales order list', function () {
    $this->getJson('/api/v1/sales/orders')
        ->assertStatus(401);
});

// ── Quotation authorization ──────────────────────────────────────────────────

it('returns 403 when user lacks sales.quotations.view', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    $this->actingAs($user)
        ->getJson('/api/v1/sales/quotations')
        ->assertStatus(403);
});

it('allows user with sales.quotations.view to list quotations', function () {
    $user = User::factory()->create();
    $user->assignRole('officer');
    $user->givePermissionTo('sales.quotations.view');

    $this->actingAs($user)
        ->getJson('/api/v1/sales/quotations')
        ->assertStatus(200);
});

it('returns 403 when user without sales.quotations.create tries to create', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $user->givePermissionTo('sales.quotations.view');

    $customer = Customer::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/sales/quotations', [
            'customer_id' => $customer->id,
            'validity_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['item_id' => 1, 'quantity' => 1, 'unit_price_centavos' => 10000],
            ],
        ])
        ->assertStatus(403);
});

// ── Sales Order SoD ──────────────────────────────────────────────────────────

it('enforces SoD on sales order confirmation -- creator cannot confirm own order', function () {
    $creator = User::factory()->create();
    $creator->assignRole('officer');
    $creator->givePermissionTo([
        'sales.orders.view',
        'sales.orders.manage',
        'sales.orders.confirm',
    ]);

    $customer = Customer::factory()->create();

    $order = SalesOrder::create([
        'order_number' => 'SO-TEST-001',
        'customer_id' => $customer->id,
        'status' => 'draft',
        'total_centavos' => 100000,
        'created_by_id' => $creator->id,
    ]);

    // Creator tries to confirm their own order -- should be blocked by SoD
    $this->actingAs($creator)
        ->patchJson("/api/v1/sales/orders/{$order->ulid}/confirm")
        ->assertStatus(403);
});

it('allows different user to confirm a sales order', function () {
    $creator = User::factory()->create();
    $creator->assignRole('officer');
    $creator->givePermissionTo(['sales.orders.view', 'sales.orders.manage']);

    $approver = User::factory()->create();
    $approver->assignRole('manager');
    $approver->givePermissionTo(['sales.orders.view', 'sales.orders.confirm']);

    $customer = Customer::factory()->create();

    $order = SalesOrder::create([
        'order_number' => 'SO-TEST-002',
        'customer_id' => $customer->id,
        'status' => 'draft',
        'total_centavos' => 100000,
        'created_by_id' => $creator->id,
    ]);

    // Different user confirms -- should succeed
    $this->actingAs($approver)
        ->patchJson("/api/v1/sales/orders/{$order->ulid}/confirm")
        ->assertSuccessful();
});
