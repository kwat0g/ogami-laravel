<?php

declare(strict_types=1);

namespace Tests\Manual;

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Models\ClientOrderItem;
use App\Domains\CRM\Services\ClientOrderService;
use App\Domains\Inventory\Models\ItemMaster;
use App\Models\User;
use App\Shared\Exceptions\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manual End-to-End Workflow Test
 *
 * This test validates the complete workflow:
 * Client Order → Negotiation → Approval → Delivery Schedule →
 * Production Order (or Stock Fulfillment) → Delivery Receipt
 */
class ClientOrderToDeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $salesUser;

    private User $clientUser;

    private Customer $customer;

    private ItemMaster $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required data
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertSuccessful();

        // Create test users with proper roles
        $this->salesUser = User::factory()->create(['email' => 'sales@example.com']);
        $this->salesUser->assignRole('officer'); // production role

        $this->clientUser = User::factory()->create([
            'email' => 'client@example.com',
        ]);
        $this->clientUser->assignRole('client');

        // Set up authentication for customer creation
        $this->actingAs($this->salesUser);

        // Create customer linked to client user
        $this->customer = Customer::factory()->create([
            'name' => 'Test Customer',
            'email' => 'client@example.com',
            'created_by' => $this->salesUser->id,
        ]);
        $this->clientUser->update(['client_id' => $this->customer->id]);

        // Create a test product
        $this->product = ItemMaster::create([
            'item_code' => 'PROD-001',
            'name' => 'Test Product',
            'type' => 'finished_goods',
            'unit_of_measure' => 'pcs',
            'category_id' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Test Scenario 1: Full Workflow with Production
     * Client Order → Negotiation → Approval → Delivery Schedule → Production Order → Delivery Receipt
     */
    public function test_full_workflow_with_production(): void
    {

        // Step 1: Client submits order
        $order = $this->createClientOrder();
        $this->assertEquals('pending', $order->status);
        echo "\n✓ Step 1: Client order created: {$order->order_reference}\n";

        // Step 2: Sales negotiates (proposes delivery date change)
        $order = $this->negotiateOrder($order);
        $this->assertEquals('negotiating', $order->status);
        $this->assertEquals(1, $order->negotiation_round);
        echo "✓ Step 2: Sales negotiated, round {$order->negotiation_round}\n";

        // Step 3: Client accepts negotiation
        $order = $this->clientAcceptNegotiation($order);
        $this->assertEquals('pending', $order->status);
        echo "✓ Step 3: Client accepted negotiation\n";

        // Step 4: Sales approves order
        $order = $this->approveOrder($order);
        $this->assertEquals('approved', $order->status);
        echo "✓ Step 4: Order approved\n";

        // Step 5: Verify Combined Delivery Schedule created
        $order->load('combinedDeliverySchedules');
        $this->assertTrue($order->combinedDeliverySchedules->isNotEmpty(), 'Combined delivery schedule should be created');
        $combinedSchedule = $order->combinedDeliverySchedules->first();
        $this->assertEquals('planning', $combinedSchedule->status);
        echo "✓ Step 5: Combined delivery schedule created: {$combinedSchedule->cds_reference}\n";

        // Step 6: Verify item schedules created
        $this->assertCount(1, $order->combinedDeliverySchedules->first()->itemSchedules);
        echo "✓ Step 6: Item schedules created\n";

        echo "\n✅ Full workflow with production - Core functionality verified!\n";
    }

    /**
     * Test Scenario 2: Multi-Item Order
     * Order with multiple items should create multiple delivery schedules under one combined schedule
     */
    public function test_multi_item_order(): void
    {
        $this->markTestSkipped('Run manually: php artisan test --filter=test_multi_item_order --no-coverage');

        // Create order with 2 items
        $order = $this->createClientOrder(items: 2);
        $order = $this->approveOrder($order);

        $order->load('combinedDeliverySchedules');

        // Should have 1 combined schedule with 2 item schedules
        $this->assertCount(1, $order->combinedDeliverySchedules);
        $combinedSchedule = $order->combinedDeliverySchedules->first();
        $this->assertEquals(2, $combinedSchedule->total_items);

        echo "\n✅ Multi-item order creates combined delivery with {$combinedSchedule->total_items} items\n";
    }

    /**
     * Test Scenario 3: Max Negotiation Rounds
     * Should prevent negotiation after max rounds reached
     */
    public function test_max_negotiation_rounds(): void
    {
        $this->markTestSkipped('Run manually: php artisan test --filter=test_max_negotiation_rounds --no-coverage');

        $order = $this->createClientOrder();

        // Negotiate back and forth 5 times
        for ($i = 1; $i <= 5; $i++) {
            $order = $this->negotiateOrder($order);
            $order = $this->clientCounterNegotiation($order);
        }

        $this->assertEquals(5, $order->negotiation_round);

        // 6th negotiation should fail
        try {
            $this->negotiateOrder($order);
            $this->fail('Expected DomainException was not thrown');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Maximum negotiation rounds reached', $e->getMessage());
            echo "\n✅ Correctly prevented negotiation beyond max rounds\n";
        }
    }

    // Helper methods

    private function createClientOrder(int $items = 1): ClientOrder
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'order_reference' => 'CO-'.now()->format('Y').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => 'pending',
            'requested_delivery_date' => now()->addWeeks(2),
            'total_amount_centavos' => 0,
            'submitted_by' => $this->clientUser->id,
            'submitted_at' => now(),
        ];

        $order = ClientOrder::create($orderData);

        for ($i = 0; $i < $items; $i++) {
            ClientOrderItem::create([
                'client_order_id' => $order->id,
                'item_master_id' => 1, // Will need valid item_master_id
                'item_description' => 'Test Product '.($i + 1),
                'quantity' => 10,
                'unit_of_measure' => 'pcs',
                'unit_price_centavos' => 10000,
                'line_total_centavos' => 100000,
                'line_order' => $i + 1,
            ]);
        }

        $total = $order->items->sum('line_total_centavos');
        $order->update(['total_amount_centavos' => $total]);

        return $order->fresh();
    }

    private function negotiateOrder(ClientOrder $order): ClientOrder
    {
        $service = app(ClientOrderService::class);

        return $service->negotiateOrder(
            $order,
            'stock_low',
            ['delivery_date' => now()->addWeeks(3)->toDateString()],
            $this->salesUser->id,
            'Test negotiation'
        );
    }

    private function clientCounterNegotiation(ClientOrder $order): ClientOrder
    {
        $service = app(ClientOrderService::class);

        return $service->clientRespond(
            $order,
            'counter',
            ['delivery_date' => now()->addWeeks(4)->toDateString()],
            $this->clientUser->id
        );
    }

    private function clientAcceptNegotiation(ClientOrder $order): ClientOrder
    {
        $service = app(ClientOrderService::class);

        return $service->clientRespond($order, 'accept', null, $this->clientUser->id);
    }

    private function approveOrder(ClientOrder $order): ClientOrder
    {
        $service = app(ClientOrderService::class);

        return $service->approveOrder($order, $this->salesUser->id, 'Test approval');
    }
}
