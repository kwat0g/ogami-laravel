<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\AR\Models\Customer;
use App\Domains\CRM\Services\ClientOrderService;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Services\CombinedDeliveryScheduleService;
use App\Domains\Production\Services\DeliveryScheduleService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Real Workflow Test Data Seeder
 *
 * Creates test data using actual service classes to ensure
 * data integrity and proper state transitions.
 */
class RealWorkflowTestSeeder extends Seeder
{
    private ClientOrderService $clientOrderService;

    private CombinedDeliveryScheduleService $combinedDeliveryService;

    private DeliveryScheduleService $deliveryScheduleService;

    private ProductionOrderService $productionOrderService;

    private User $salesUser;

    private User $clientUser;

    private Customer $customer;

    private array $products = [];

    public function run(): void
    {
        $this->command->info('Creating Real Workflow Test Data...');

        // Initialize services
        $this->clientOrderService = app(ClientOrderService::class);
        $this->combinedDeliveryService = app(CombinedDeliveryScheduleService::class);
        $this->deliveryScheduleService = app(DeliveryScheduleService::class);
        $this->productionOrderService = app(ProductionOrderService::class);

        // Get or create test users
        $this->createTestUsers();
        $this->command->info('Created users: sales@ogami.test / client@ogami.test');

        // Create test customer
        $this->createTestCustomer();
        $this->command->info("Created customer: {$this->customer->name}");

        // Create products with BOMs
        $this->createProducts();
        $this->command->info('Created '.count($this->products).' products with BOMs');

        // Add stock for stock fulfillment scenario
        $this->addStockToWarehouse();

        // Run scenarios using actual services
        $this->command->info('');
        $this->command->info('Running workflow scenarios...');

        // Scenario 1: Complete workflow - order → negotiation → approval → production → delivery
        $this->scenario1_CompleteWorkflowWithProduction();

        // Scenario 2: Stock fulfillment - order → approval → stock fulfillment
        $this->scenario2_StockFulfillment();

        // Scenario 3: In negotiation
        $this->scenario3_InNegotiation();

        // Scenario 4: Multi-item partially ready
        $this->scenario4_MultiItemPartiallyReady();

        // Scenario 5: Delivered awaiting acknowledgment
        $this->scenario5_DeliveredAwaitingAcknowledgment();

        $this->command->info('');
        $this->command->info('==============================================');
        $this->command->info('✅ Real Workflow Test Data Created!');
        $this->command->info('==============================================');
        $this->command->info('');
        $this->command->info('Test Accounts:');
        $this->command->info('  Sales: sales@ogami.test / Sales@123');
        $this->command->info('  Client: client@ogami.test / Client@123');
    }

    private function createTestUsers(): void
    {
        $this->salesUser = User::where('email', 'sales@ogami.test')->first()
            ?? User::factory()->create([
                'name' => 'Sales Manager',
                'email' => 'sales@ogami.test',
                'password' => bcrypt('Sales@123'),
            ]);
        $this->salesUser->assignRole('officer');

        $this->clientUser = User::where('email', 'client@ogami.test')->first()
            ?? User::factory()->create([
                'name' => 'Test Client User',
                'email' => 'client@ogami.test',
                'password' => bcrypt('Client@123'),
            ]);
        $this->clientUser->assignRole('client');
    }

    private function createTestCustomer(): void
    {
        $this->customer = Customer::factory()->create([
            'name' => 'ABC Manufacturing Corp',
            'email' => 'purchasing@abc-mfg.com',
            'phone' => '+63 2 8123 4567',
            'address' => '123 Industrial Ave, Makati City, Metro Manila',
            'contact_person' => 'Juan Dela Cruz',
            'created_by' => $this->salesUser->id,
        ]);

        $this->clientUser->update(['client_id' => $this->customer->id]);
    }

    private function createProducts(): void
    {
        $category = ItemCategory::firstOrCreate(
            ['code' => 'FG'],
            ['name' => 'Finished Goods', 'description' => 'Finished products ready for sale']
        );

        $productData = [
            ['code' => 'FG-PLASTIC-001', 'name' => 'Plastic Component A', 'price' => 15000],
            ['code' => 'FG-METAL-002', 'name' => 'Metal Bracket B', 'price' => 28550],
            ['code' => 'FG-ASSY-003', 'name' => 'Assembly Unit C', 'price' => 125000],
        ];

        foreach ($productData as $data) {
            $product = ItemMaster::firstOrCreate(
                ['item_code' => $data['code']],
                [
                    'name' => $data['name'],
                    'type' => 'finished_goods',
                    'unit_of_measure' => 'pcs',
                    'category_id' => $category->id,
                    'is_active' => true,
                ]
            );

            // Create BOM
            BillOfMaterials::firstOrCreate(
                ['product_item_id' => $product->id],
                [
                    'version' => '1.0',
                    'is_active' => true,
                    'notes' => 'Standard BOM for '.$product->name,
                ]
            );

            $this->products[] = $product;
        }
    }

    private function addStockToWarehouse(): void
    {
        $location = WarehouseLocation::firstOrCreate(
            ['code' => 'MAIN-WH'],
            ['name' => 'Main Warehouse', 'is_active' => true]
        );

        // Add stock to second product (Metal Bracket B) for stock fulfillment
        StockBalance::firstOrCreate(
            ['item_id' => $this->products[1]->id, 'location_id' => $location->id],
            ['quantity_on_hand' => 500]
        );
    }

    /**
     * Scenario 1: Complete workflow with production
     * Order → Submit → Negotiate → Accept → Approve → Production → Ready
     */
    private function scenario1_CompleteWorkflowWithProduction(): void
    {
        $this->command->info('  → Scenario 1: Complete workflow with production...');

        // Step 1: Client submits order
        $order = $this->clientOrderService->submitOrder(
            customerId: $this->customer->id,
            items: [
                [
                    'item_master_id' => $this->products[0]->id,
                    'quantity' => 10,
                    'unit_price_centavos' => 15000,
                ],
            ],
            requestedDate: now()->addWeeks(2)->toDateString(),
            notes: 'Need this urgently for production line',
            submittedByUserId: $this->clientUser->id
        );

        // Step 2: Sales negotiates (requests later delivery)
        $order = $this->clientOrderService->negotiateOrder(
            order: $order,
            reason: 'production_capacity',
            proposedChanges: ['delivery_date' => now()->addWeeks(3)->toDateString()],
            reviewerId: $this->salesUser->id,
            notes: 'Need 3 weeks due to material lead time'
        );

        // Step 3: Client accepts negotiation
        $order = $this->clientOrderService->clientRespond(
            order: $order,
            response: 'accept',
            counterProposals: null,
            clientUserId: $this->clientUser->id
        );

        // Step 4: Sales approves order (this creates combined delivery schedule)
        $order = $this->clientOrderService->approveOrder(
            order: $order,
            reviewerId: $this->salesUser->id,
            notes: 'Approved with negotiated terms'
        );

        // Reload to get delivery schedules
        $order->load('combinedDeliverySchedules');
        $combinedSchedule = $order->combinedDeliverySchedules->first();

        if ($combinedSchedule) {
            // Step 5: Create production order for the item
            $itemSchedule = $combinedSchedule->itemSchedules->first();
            if ($itemSchedule) {
                $bom = BillOfMaterials::where('product_item_id', $itemSchedule->product_item_id)->first();

                $productionOrder = $this->productionOrderService->create([
                    'delivery_schedule_id' => $itemSchedule->id,
                    'product_item_id' => $itemSchedule->product_item_id,
                    'bom_id' => $bom?->id,
                    'qty_required' => $itemSchedule->qty_ordered,
                    'target_start_date' => now()->addDay()->toDateString(),
                    'target_end_date' => now()->addWeek()->toDateString(),
                    'notes' => 'Production for client order',
                ], $this->salesUser->id);

                // Simulate partial production
                $productionOrder->update(['qty_produced' => 3, 'status' => 'in_progress']);

                // Update delivery schedule status
                $itemSchedule->update(['status' => 'in_production']);
            }

            // Update combined schedule status
            $combinedSchedule->updateItemStatusSummary();
        }

        $this->command->info("     ✓ Order {$order->order_reference} - {$order->status} - In production");
    }

    /**
     * Scenario 2: Stock fulfillment (no production needed)
     * Order → Approve → Stock fulfillment → Ready
     */
    private function scenario2_StockFulfillment(): void
    {
        $this->command->info('  → Scenario 2: Stock fulfillment...');

        // Submit order for product with stock
        $order = $this->clientOrderService->submitOrder(
            customerId: $this->customer->id,
            items: [
                [
                    'item_master_id' => $this->products[1]->id, // Metal Bracket B with stock
                    'quantity' => 10,
                    'unit_price_centavos' => 28550,
                ],
            ],
            requestedDate: now()->addWeek()->toDateString(),
            submittedByUserId: $this->clientUser->id
        );

        // Approve immediately
        $order = $this->clientOrderService->approveOrder(
            order: $order,
            reviewerId: $this->salesUser->id,
            notes: 'Approved - Stock available'
        );

        // Get combined schedule
        $order->load('combinedDeliverySchedules');
        $combinedSchedule = $order->combinedDeliverySchedules->first();

        if ($combinedSchedule) {
            $itemSchedule = $combinedSchedule->itemSchedules->first();

            if ($itemSchedule) {
                // Fulfill from stock
                $this->deliveryScheduleService->fulfillFromStock(
                    deliverySchedule: $itemSchedule,
                    userId: $this->salesUser->id
                );
            }

            $combinedSchedule->updateItemStatusSummary();
        }

        $this->command->info("     ✓ Order {$order->order_reference} - {$order->status} - Fulfilled from stock");
    }

    /**
     * Scenario 3: Order in negotiation
     */
    private function scenario3_InNegotiation(): void
    {
        $this->command->info('  → Scenario 3: Order in negotiation...');

        $order = $this->clientOrderService->submitOrder(
            customerId: $this->customer->id,
            items: [
                [
                    'item_master_id' => $this->products[2]->id,
                    'quantity' => 5,
                    'unit_price_centavos' => 125000,
                ],
            ],
            requestedDate: now()->addWeeks(2)->toDateString(),
            submittedByUserId: $this->clientUser->id
        );

        // Sales negotiates
        $order = $this->clientOrderService->negotiateOrder(
            order: $order,
            reason: 'production_delay',
            proposedChanges: ['delivery_date' => now()->addWeeks(4)->toDateString()],
            reviewerId: $this->salesUser->id,
            notes: 'Material shortage - need 4 weeks'
        );

        $this->command->info("     ✓ Order {$order->order_reference} - {$order->status} - Waiting for client response");
    }

    /**
     * Scenario 4: Multi-item partially ready
     */
    private function scenario4_MultiItemPartiallyReady(): void
    {
        $this->command->info('  → Scenario 4: Multi-item partially ready...');

        $order = $this->clientOrderService->submitOrder(
            customerId: $this->customer->id,
            items: [
                [
                    'item_master_id' => $this->products[0]->id,
                    'quantity' => 5,
                    'unit_price_centavos' => 15000,
                ],
                [
                    'item_master_id' => $this->products[1]->id,
                    'quantity' => 8,
                    'unit_price_centavos' => 28550,
                ],
                [
                    'item_master_id' => $this->products[2]->id,
                    'quantity' => 2,
                    'unit_price_centavos' => 125000,
                ],
            ],
            requestedDate: now()->addWeeks(3)->toDateString(),
            submittedByUserId: $this->clientUser->id
        );

        // Approve
        $order = $this->clientOrderService->approveOrder(
            order: $order,
            reviewerId: $this->salesUser->id
        );

        // Get combined schedule
        $order->load('combinedDeliverySchedules');
        $combinedSchedule = $order->combinedDeliverySchedules->first();

        if ($combinedSchedule) {
            // Mark first item as ready (fulfill from stock or production complete)
            $itemSchedules = $combinedSchedule->itemSchedules;

            // First item - create production and complete
            $firstItem = $itemSchedules->get(0);
            if ($firstItem) {
                $bom = BillOfMaterials::where('product_item_id', $firstItem->product_item_id)->first();
                $po = $this->productionOrderService->create([
                    'delivery_schedule_id' => $firstItem->id,
                    'product_item_id' => $firstItem->product_item_id,
                    'bom_id' => $bom?->id,
                    'qty_required' => $firstItem->qty_ordered,
                    'target_start_date' => now()->subWeek()->toDateString(),
                    'target_end_date' => now()->subDay()->toDateString(),
                ], $this->salesUser->id);

                // Complete production
                $this->productionOrderService->complete($po, $this->salesUser->id);
                $firstItem->update(['status' => 'ready']);
            }

            // Second item - fulfill from stock
            $secondItem = $itemSchedules->get(1);
            if ($secondItem) {
                $this->deliveryScheduleService->fulfillFromStock($secondItem, $this->salesUser->id);
            }

            // Third item - still in production (leave as open)

            $combinedSchedule->updateItemStatusSummary();
        }

        $this->command->info("     ✓ Order {$order->order_reference} - {$order->status} - Partially ready");
    }

    /**
     * Scenario 5: Delivered awaiting acknowledgment
     */
    private function scenario5_DeliveredAwaitingAcknowledgment(): void
    {
        $this->command->info('  → Scenario 5: Delivered awaiting acknowledgment...');

        $order = $this->clientOrderService->submitOrder(
            customerId: $this->customer->id,
            items: [
                [
                    'item_master_id' => $this->products[0]->id,
                    'quantity' => 4,
                    'unit_price_centavos' => 15000,
                ],
            ],
            requestedDate: now()->addWeek()->toDateString(),
            submittedByUserId: $this->clientUser->id
        );

        // Approve
        $order = $this->clientOrderService->approveOrder(
            order: $order,
            reviewerId: $this->salesUser->id
        );

        // Get combined schedule
        $order->load('combinedDeliverySchedules');
        $combinedSchedule = $order->combinedDeliverySchedules->first();

        if ($combinedSchedule) {
            // Complete production and mark ready
            $itemSchedule = $combinedSchedule->itemSchedules->first();
            if ($itemSchedule) {
                $bom = BillOfMaterials::where('product_item_id', $itemSchedule->product_item_id)->first();
                $po = $this->productionOrderService->create([
                    'delivery_schedule_id' => $itemSchedule->id,
                    'product_item_id' => $itemSchedule->product_item_id,
                    'bom_id' => $bom?->id,
                    'qty_required' => $itemSchedule->qty_ordered,
                    'target_start_date' => now()->subWeek()->toDateString(),
                    'target_end_date' => now()->subDays(2)->toDateString(),
                ], $this->salesUser->id);

                $this->productionOrderService->complete($po, $this->salesUser->id);
                $itemSchedule->update(['status' => 'ready']);
            }

            $combinedSchedule->updateItemStatusSummary();

            // Dispatch the delivery
            $this->combinedDeliveryService->dispatch(
                schedule: $combinedSchedule,
                userId: $this->salesUser->id,
                vehicleId: null,
                driverName: 'Mang Juan',
                deliveryNotes: 'Deliver to warehouse gate'
            );

            // Mark as delivered
            $combinedSchedule = $this->combinedDeliveryService->markDelivered(
                schedule: $combinedSchedule,
                deliveryDate: now()->subDay()->toDateString(),
                userId: $this->salesUser->id
            );
        }

        $this->command->info("     ✓ Order {$order->order_reference} - {$order->status} - Delivered, awaiting client acknowledgment");
    }
}
