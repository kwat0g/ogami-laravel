<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\AR\Models\Customer;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Production\Models\BillOfMaterials;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Test data seeder for Client Order → Delivery → Invoice workflow
 */
class ClientOrderWorkflowTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating Client Order Reference Data (users, customer, products, BOMs, stock)...');

        // Get or create test users
        $salesUser = User::firstOrCreate(
            ['email' => 'sales@ogami.test'],
            ['name' => 'Sales Manager', 'password' => bcrypt('Sales@123')]
        );
        $salesUser->assignRole('officer');

        $clientUser = User::firstOrCreate(
            ['email' => 'client@ogami.test'],
            ['name' => 'Test Client User', 'password' => bcrypt('Client@123')]
        );
        $clientUser->assignRole('client');

        // Create test customer — high credit limit to support VP-threshold test scenarios (₱10M)
        $customer = Customer::firstOrCreate(
            ['email' => 'purchasing@abc-mfg.com'],
            [
                'name' => 'ABC Manufacturing Corp',
                'phone' => '+63 2 8123 4567',
                'address' => '123 Industrial Ave, Makati City, Metro Manila',
                'contact_person' => 'Juan Dela Cruz',
                'credit_limit' => 10000000.00,
                'is_active' => true,
                'created_by' => $salesUser->id,
            ]
        );
        $clientUser->update(['client_id' => $customer->id]);

        $this->command->info("  ✓ Customer: {$customer->name}");

        // Create or get item category
        $category = ItemCategory::firstOrCreate(
            ['code' => 'FG'],
            ['name' => 'Finished Goods', 'description' => 'Finished products ready for sale']
        );

        // Create products
        $products = $this->createProducts($salesUser->id, $category->id);
        $this->command->info('  ✓ '.count($products).' FG item masters');

        // Create BOMs for products
        $this->createBOMs($products, $salesUser->id);
        $this->command->info('  ✓ BOMs created');

        // Add stock to warehouse for some products
        $this->addStock($products, $salesUser->id);
        $this->command->info('  ✓ Initial stock seeded');

        $this->command->info('✓ Client Order reference data ready — no transactional orders seeded.');
        $this->command->info('  Login: client@ogami.test / Client@123 | Customer: ABC Manufacturing Corp');
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
            BillOfMaterials::firstOrCreate(
                ['product_item_id' => $product->id, 'version' => '1.0'],
                [
                    'is_active' => true,
                    'notes' => 'Standard BOM for '.$product->name,
                ]
            );
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
}
