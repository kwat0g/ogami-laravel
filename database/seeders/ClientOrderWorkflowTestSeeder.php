<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Models\ClientOrderItem;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\CombinedDeliverySchedule;
use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Test data seeder for Client Order → Delivery → Invoice workflow
 */
class ClientOrderWorkflowTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating Client Order Workflow Test Data...');

        // Get or create test users
        $salesUser = User::where('email', 'sales@ogami.test')->first()
            ?? User::factory()->create([
                'name' => 'Sales Manager',
                'email' => 'sales@ogami.test',
                'password' => bcrypt('Sales@123'),
            ]);
        $salesUser->assignRole('officer');

        $clientUser = User::where('email', 'client@ogami.test')->first()
            ?? User::factory()->create([
                'name' => 'Test Client User',
                'email' => 'client@ogami.test',
                'password' => bcrypt('Client@123'),
            ]);
        $clientUser->assignRole('client');

        // Create test customer — high credit limit to support VP-threshold test scenarios (₱10M)
        $customer = Customer::factory()->create([
            'name' => 'ABC Manufacturing Corp',
            'email' => 'purchasing@abc-mfg.com',
            'phone' => '+63 2 8123 4567',
            'address' => '123 Industrial Ave, Makati City, Metro Manila',
            'contact_person' => 'Juan Dela Cruz',
            'credit_limit' => 10000000.00,
            'created_by' => $salesUser->id,
        ]);
        $clientUser->update(['client_id' => $customer->id]);

        $this->command->info("Created customer: {$customer->name}");

        // Create or get item category
        $category = ItemCategory::firstOrCreate(
            ['code' => 'FG'],
            ['name' => 'Finished Goods', 'description' => 'Finished products ready for sale']
        );

        // Create products
        $products = $this->createProducts($salesUser->id, $category->id);
        $this->command->info('Created '.count($products).' products');

        // Create BOMs for products
        $this->createBOMs($products, $salesUser->id);
        $this->command->info('Created BOMs');

        // Add stock to warehouse for some products
        $this->addStock($products, $salesUser->id);
        $this->command->info('Added stock to warehouse');

        // Scenario 1: Simple order (1 item) - Approved, in production
        $this->createScenario1_SimpleOrder($customer, $products[0], $salesUser->id, $clientUser->id);
        $this->command->info('✓ Scenario 1: Simple order (1 item, in production)');

        // Scenario 2: Multi-item order (3 items) - Approved, partial production
        $this->createScenario2_MultiItem($customer, $products, $salesUser->id, $clientUser->id);
        $this->command->info('✓ Scenario 2: Multi-item order (3 items, partial)');

        // Scenario 3: Order with stock fulfillment (no production needed)
        $this->createScenario3_StockFulfillment($customer, $products[1], $salesUser->id, $clientUser->id);
        $this->command->info('✓ Scenario 3: Stock fulfillment (no production)');

        // Scenario 4: Order in negotiation
        $this->createScenario4_Negotiation($customer, $products[2], $salesUser->id, $clientUser->id);
        $this->command->info('✓ Scenario 4: Order in negotiation');

        // Scenario 5: Order delivered, awaiting client acknowledgment
        $this->createScenario5_Delivered($customer, $products[0], $salesUser->id, $clientUser->id);
        $this->command->info('✓ Scenario 5: Delivered, awaiting acknowledgment');

        // Scenario 6: Completed order (delivered + acknowledged + invoiced)
        $this->createScenario6_Completed($customer, $products[2], $salesUser->id, $clientUser->id);
        $this->command->info('✓ Scenario 6: Completed order');

        $this->command->info('');
        $this->command->info('==============================================');
        $this->command->info('Test Data Created Successfully!');
        $this->command->info('==============================================');
        $this->command->info('');
        $this->command->info('Test Accounts:');
        $this->command->info('  Sales: sales@ogami.test');
        $this->command->info('  Client: client@ogami.test / ABC Manufacturing Corp');
        $this->command->info('');
        $this->command->info('Scenarios Created:');
        $this->command->info('  1. Simple order - Approved, production in progress');
        $this->command->info('  2. Multi-item order - Approved, partially ready');
        $this->command->info('  3. Stock fulfillment - Approved, ready to ship');
        $this->command->info('  4. In negotiation - Pending client response');
        $this->command->info('  5. Delivered - Awaiting client acknowledgment');
        $this->command->info('  6. Completed - Delivered, acknowledged, invoiced');
    }

    private function createProducts(int $userId, int $categoryId): array
    {
        $products = [];

        $productData = [
            [
                'item_code' => 'FG-PLASTIC-001',
                'name' => 'Plastic Component A',
                'type' => 'finished_good',
                'unit_of_measure' => 'pcs',
                'description' => 'High-grade plastic component for manufacturing',
                'unit_price' => 150.00,
            ],
            [
                'item_code' => 'FG-METAL-002',
                'name' => 'Metal Bracket B',
                'type' => 'finished_good',
                'unit_of_measure' => 'pcs',
                'description' => 'Stainless steel mounting bracket',
                'unit_price' => 285.50,
            ],
            [
                'item_code' => 'FG-ASSY-003',
                'name' => 'Assembly Unit C',
                'type' => 'finished_good',
                'unit_of_measure' => 'set',
                'description' => 'Complete assembly unit with mounting hardware',
                'unit_price' => 1250.00,
            ],
        ];

        foreach ($productData as $data) {
            // Check if product already exists
            $existing = ItemMaster::where('item_code', $data['item_code'])->first();
            if ($existing) {
                $products[] = $existing;
            } else {
                $products[] = ItemMaster::create([
                    'item_code' => $data['item_code'],
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'unit_of_measure' => $data['unit_of_measure'],
                    'description' => $data['description'],
                    'category_id' => $categoryId,
                    'is_active' => true,
                ]);
            }
        }

        return $products;
    }

    private function createBOMs(array $products, int $userId): void
    {
        foreach ($products as $product) {
            BillOfMaterials::create([
                'product_item_id' => $product->id,
                'version' => '1.0',
                'is_active' => true,
                'notes' => 'Standard BOM for '.$product->name,
            ]);
        }
    }

    private function addStock(array $products, int $userId): void
    {
        $location = WarehouseLocation::firstOrCreate(
            ['code' => 'MAIN-WH'],
            ['name' => 'Main Warehouse', 'is_active' => true]
        );

        // Add stock to second product only (for stock fulfillment scenario)
        // Check if stock balance already exists
        $existingBalance = StockBalance::where('item_id', $products[1]->id)
            ->where('location_id', $location->id)
            ->first();

        if (! $existingBalance) {
            StockBalance::create([
                'item_id' => $products[1]->id,
                'location_id' => $location->id,
                'quantity_on_hand' => 500,
            ]);
        }
    }

    private function createScenario1_SimpleOrder(Customer $customer, ItemMaster $product, int $salesUserId, int $clientUserId): void
    {
        $order = ClientOrder::create([
            'customer_id' => $customer->id,
            'order_reference' => 'CO-2026-00001',
            'status' => 'approved',
            'requested_delivery_date' => now()->addWeeks(2),
            'agreed_delivery_date' => now()->addWeeks(3),
            'total_amount_centavos' => 150000, // 150 * 100 * 10
            'submitted_by' => $clientUserId,
            'submitted_at' => now()->subDays(5),
            'approved_by' => $salesUserId,
            'approved_at' => now()->subDays(3),
        ]);

        ClientOrderItem::create([
            'client_order_id' => $order->id,
            'item_master_id' => $product->id,
            'item_description' => $product->name,
            'quantity' => 10,
            'unit_of_measure' => 'pcs',
            'unit_price_centavos' => 15000,
            'line_total_centavos' => 150000,
            'line_order' => 1,
        ]);

        // Create combined delivery schedule
        $combinedSchedule = CombinedDeliverySchedule::create([
            'client_order_id' => $order->id,
            'customer_id' => $customer->id,
            'cds_reference' => 'CDS-2026-00001',
            'status' => CombinedDeliverySchedule::STATUS_PLANNING,
            'target_delivery_date' => $order->agreed_delivery_date,
            'total_items' => 1,
            'ready_items' => 0,
            'missing_items' => 1,
            'created_by_id' => $salesUserId,
        ]);

        // Create item schedule
        $itemSchedule = DeliverySchedule::create([
            'customer_id' => $customer->id,
            'product_item_id' => $product->id,
            'qty_ordered' => 10,
            'target_delivery_date' => $order->agreed_delivery_date,
            'type' => 'local',
            'status' => 'open',
            'combined_delivery_schedule_id' => $combinedSchedule->id,
        ]);

        // Create production order
        ProductionOrder::create([
            'delivery_schedule_id' => $itemSchedule->id,
            'product_item_id' => $product->id,
            'bom_id' => BillOfMaterials::where('product_item_id', $product->id)->first()->id,
            'po_reference' => 'PO-2026-00001',
            'qty_required' => 10,
            'qty_produced' => 3, // Partial production
            'target_start_date' => now()->subDays(2),
            'target_end_date' => now()->addDays(5),
            'status' => 'in_progress',
            'created_by_id' => $salesUserId,
        ]);

        $combinedSchedule->updateItemStatusSummary();
    }

    private function createScenario2_MultiItem(Customer $customer, array $products, int $salesUserId, int $clientUserId): void
    {
        $order = ClientOrder::create([
            'customer_id' => $customer->id,
            'order_reference' => 'CO-2026-00002',
            'status' => 'approved',
            'requested_delivery_date' => now()->addWeeks(3),
            'agreed_delivery_date' => now()->addWeeks(4),
            'total_amount_centavos' => 536050, // Mixed totals
            'submitted_by' => $clientUserId,
            'submitted_at' => now()->subDays(7),
            'approved_by' => $salesUserId,
            'approved_at' => now()->subDays(5),
        ]);

        $quantities = [5, 10, 2];
        $prices = [15000, 28550, 125000];

        foreach ($products as $i => $product) {
            ClientOrderItem::create([
                'client_order_id' => $order->id,
                'item_master_id' => $product->id,
                'item_description' => $product->name,
                'quantity' => $quantities[$i],
                'unit_of_measure' => $product->unit_of_measure,
                'unit_price_centavos' => $prices[$i],
                'line_total_centavos' => $quantities[$i] * $prices[$i],
                'line_order' => $i + 1,
            ]);
        }

        // Combined schedule - partially ready
        $combinedSchedule = CombinedDeliverySchedule::create([
            'client_order_id' => $order->id,
            'customer_id' => $customer->id,
            'cds_reference' => 'CDS-2026-00002',
            'status' => CombinedDeliverySchedule::STATUS_PARTIALLY_READY,
            'target_delivery_date' => $order->agreed_delivery_date,
            'total_items' => 3,
            'ready_items' => 1, // Only first item ready
            'missing_items' => 2,
            'created_by_id' => $salesUserId,
        ]);

        // Create item schedules with different statuses
        foreach ($products as $i => $product) {
            $status = $i === 0 ? 'ready' : 'in_production';

            $itemSchedule = DeliverySchedule::create([
                'customer_id' => $customer->id,
                'product_item_id' => $product->id,
                'qty_ordered' => $quantities[$i],
                'target_delivery_date' => $order->agreed_delivery_date,
                'type' => 'local',
                'status' => $status,
                'combined_delivery_schedule_id' => $combinedSchedule->id,
            ]);

            // Production for first item only (completed)
            if ($i === 0) {
                ProductionOrder::create([
                    'delivery_schedule_id' => $itemSchedule->id,
                    'product_item_id' => $product->id,
                    'bom_id' => BillOfMaterials::where('product_item_id', $product->id)->first()->id,
                    'po_reference' => 'PO-2026-0000'.($i + 2),
                    'qty_required' => $quantities[$i],
                    'qty_produced' => $quantities[$i],
                    'target_start_date' => now()->subWeek(),
                    'target_end_date' => now()->subDays(2),
                    'status' => 'completed',
                    'created_by_id' => $salesUserId,
                ]);
            }
        }

        $combinedSchedule->updateItemStatusSummary();
    }

    private function createScenario3_StockFulfillment(Customer $customer, ItemMaster $product, int $salesUserId, int $clientUserId): void
    {
        $order = ClientOrder::create([
            'customer_id' => $customer->id,
            'order_reference' => 'CO-2026-00003',
            'status' => 'approved',
            'requested_delivery_date' => now()->addWeeks(1),
            'agreed_delivery_date' => now()->addDays(5),
            'total_amount_centavos' => 285500, // 285.50 * 100 * 10
            'submitted_by' => $clientUserId,
            'submitted_at' => now()->subDays(3),
            'approved_by' => $salesUserId,
            'approved_at' => now()->subDays(2),
        ]);

        ClientOrderItem::create([
            'client_order_id' => $order->id,
            'item_master_id' => $product->id,
            'item_description' => $product->name,
            'quantity' => 10,
            'unit_of_measure' => 'pcs',
            'unit_price_centavos' => 28550,
            'line_total_centavos' => 285500,
            'line_order' => 1,
        ]);

        // Combined schedule - ready (fulfilled from stock)
        $combinedSchedule = CombinedDeliverySchedule::create([
            'client_order_id' => $order->id,
            'customer_id' => $customer->id,
            'cds_reference' => 'CDS-2026-00003',
            'status' => CombinedDeliverySchedule::STATUS_READY,
            'target_delivery_date' => $order->agreed_delivery_date,
            'total_items' => 1,
            'ready_items' => 1,
            'missing_items' => 0,
            'created_by_id' => $salesUserId,
        ]);

        // Item schedule ready (stock available)
        DeliverySchedule::create([
            'customer_id' => $customer->id,
            'product_item_id' => $product->id,
            'qty_ordered' => 10,
            'target_delivery_date' => $order->agreed_delivery_date,
            'type' => 'local',
            'status' => 'ready',
            'notes' => 'Fulfilled from stock - No production needed',
            'combined_delivery_schedule_id' => $combinedSchedule->id,
        ]);

        $combinedSchedule->updateItemStatusSummary();
    }

    private function createScenario4_Negotiation(Customer $customer, ItemMaster $product, int $salesUserId, int $clientUserId): void
    {
        $order = ClientOrder::create([
            'customer_id' => $customer->id,
            'order_reference' => 'CO-2026-00004',
            'status' => 'negotiating',
            'requested_delivery_date' => now()->addWeeks(2),
            'total_amount_centavos' => 375000, // 150 * 100 * 25
            'submitted_by' => $clientUserId,
            'submitted_at' => now()->subDays(2),
            'negotiation_turn' => 'client',
            'negotiation_round' => 1,
            'negotiation_reason' => 'production_delay',
            'negotiation_notes' => 'Need to extend delivery date due to material shortage',
            'last_negotiation_by' => 'sales',
            'last_negotiation_at' => now()->subDay(),
        ]);

        ClientOrderItem::create([
            'client_order_id' => $order->id,
            'item_master_id' => $product->id,
            'item_description' => $product->name,
            'quantity' => 25,
            'unit_of_measure' => 'pcs',
            'unit_price_centavos' => 15000,
            'line_total_centavos' => 375000,
            'line_order' => 1,
        ]);
    }

    private function createScenario5_Delivered(Customer $customer, ItemMaster $product, int $salesUserId, int $clientUserId): void
    {
        $order = ClientOrder::create([
            'customer_id' => $customer->id,
            'order_reference' => 'CO-2026-00005',
            'status' => 'approved',
            'requested_delivery_date' => now()->addWeeks(1),
            'agreed_delivery_date' => now()->subDays(2),
            'total_amount_centavos' => 60000, // 150 * 100 * 4
            'submitted_by' => $clientUserId,
            'submitted_at' => now()->subWeeks(2),
            'approved_by' => $salesUserId,
            'approved_at' => now()->subWeeks(2),
        ]);

        ClientOrderItem::create([
            'client_order_id' => $order->id,
            'item_master_id' => $product->id,
            'item_description' => $product->name,
            'quantity' => 4,
            'unit_of_measure' => 'pcs',
            'unit_price_centavos' => 15000,
            'line_total_centavos' => 60000,
            'line_order' => 1,
        ]);

        // Combined schedule - delivered, awaiting acknowledgment
        $combinedSchedule = CombinedDeliverySchedule::create([
            'client_order_id' => $order->id,
            'customer_id' => $customer->id,
            'cds_reference' => 'CDS-2026-00005',
            'status' => CombinedDeliverySchedule::STATUS_DELIVERED,
            'target_delivery_date' => $order->agreed_delivery_date,
            'actual_delivery_date' => now()->subDay(),
            'total_items' => 1,
            'ready_items' => 1,
            'missing_items' => 0,
            'dispatched_by_id' => $salesUserId,
            'dispatched_at' => now()->subDays(2),
            'created_by_id' => $salesUserId,
        ]);

        // Item schedule delivered
        DeliverySchedule::create([
            'customer_id' => $customer->id,
            'product_item_id' => $product->id,
            'qty_ordered' => 4,
            'target_delivery_date' => $order->agreed_delivery_date,
            'type' => 'local',
            'status' => 'delivered',
            'combined_delivery_schedule_id' => $combinedSchedule->id,
        ]);

        $combinedSchedule->updateItemStatusSummary();
    }

    private function createScenario6_Completed(Customer $customer, ItemMaster $product, int $salesUserId, int $clientUserId): void
    {
        // Skip if order already exists
        if (ClientOrder::where('order_reference', 'CO-2026-00006')->exists()) {
            return;
        }

        $order = ClientOrder::create([
            'customer_id' => $customer->id,
            'order_reference' => 'CO-2026-00006',
            'status' => 'approved',
            'requested_delivery_date' => now()->subWeeks(2),
            'agreed_delivery_date' => now()->subWeeks(2),
            'total_amount_centavos' => 375000,
            'submitted_by' => $clientUserId,
            'submitted_at' => now()->subWeeks(4),
            'approved_by' => $salesUserId,
            'approved_at' => now()->subWeeks(3),
        ]);

        ClientOrderItem::create([
            'client_order_id' => $order->id,
            'item_master_id' => $product->id,
            'item_description' => $product->name,
            'quantity' => 3,
            'unit_of_measure' => 'set',
            'unit_price_centavos' => 125000,
            'line_total_centavos' => 375000,
            'line_order' => 1,
        ]);

        // Combined schedule - completed
        $combinedSchedule = CombinedDeliverySchedule::create([
            'client_order_id' => $order->id,
            'customer_id' => $customer->id,
            'cds_reference' => 'CDS-2026-00006',
            'status' => CombinedDeliverySchedule::STATUS_DELIVERED,
            'target_delivery_date' => $order->agreed_delivery_date,
            'actual_delivery_date' => now()->subWeeks(2),
            'total_items' => 1,
            'ready_items' => 1,
            'missing_items' => 0,
            'dispatched_by_id' => $salesUserId,
            'dispatched_at' => now()->subWeeks(2),
            'created_by_id' => $salesUserId,
        ]);

        // Item schedule delivered with acknowledgment
        $itemSchedule = DeliverySchedule::create([
            'customer_id' => $customer->id,
            'product_item_id' => $product->id,
            'qty_ordered' => 3,
            'target_delivery_date' => $order->agreed_delivery_date,
            'type' => 'local',
            'status' => 'delivered',
            'client_acknowledgment' => [
                'received_qty' => 3,
                'condition' => 'good',
                'notes' => null,
                'acknowledged_at' => now()->subWeeks(1)->toIso8601String(),
                'acknowledged_by' => $clientUserId,
            ],
            'combined_delivery_schedule_id' => $combinedSchedule->id,
        ]);

        $combinedSchedule->updateItemStatusSummary();

        // Invoice already created (total_amount is auto-generated from subtotal + vat_amount)
        $fiscalPeriodId = \App\Domains\Accounting\Models\FiscalPeriod::where('status', 'open')->value('id')
            ?? \App\Domains\Accounting\Models\FiscalPeriod::orderBy('id')->value('id');
        $arAccountId = \App\Domains\Accounting\Models\ChartOfAccount::where('name', 'LIKE', '%Accounts Receivable%')->value('id')
            ?? \App\Domains\Accounting\Models\ChartOfAccount::orderBy('id')->value('id');
        $revenueAccountId = \App\Domains\Accounting\Models\ChartOfAccount::where('name', 'LIKE', '%Revenue%')->value('id')
            ?? $arAccountId;

        CustomerInvoice::create([
            'customer_id' => $customer->id,
            'fiscal_period_id' => $fiscalPeriodId,
            'ar_account_id' => $arAccountId,
            'revenue_account_id' => $revenueAccountId,
            'invoice_number' => 'INV-2026-00001',
            'invoice_date' => now()->subWeeks(1),
            'due_date' => now()->addWeeks(3),
            'subtotal' => 3750.00,
            'vat_amount' => 450.00,
            'status' => 'draft',
            'description' => "Invoice for Order {$order->order_reference}",
            'created_by' => $salesUserId,
        ]);
    }
}
