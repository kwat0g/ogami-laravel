<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\AR\Models\Customer;
use App\Domains\Inventory\Models\ItemMaster;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder for demo/testing automation features
 */
class DemoAutomationFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating demo data for automation features...');

        // 1. Create Client Portal User
        $this->createClientUser();

        // 2. Create Finished Goods Items
        $this->createFinishedGoods();

        // 3. Create Sales User with review permission
        $this->createSalesUser();

        $this->command->info('Demo data created successfully!');
        $this->command->info('');
        $this->command->info('Test Accounts:');
        $this->command->info('  Client: client@demo.com / password');
        $this->command->info('  Sales: sales@demo.com / password');
    }

    private function createClientUser(): void
    {
        $systemUser = User::first();
        
        $customer = Customer::firstOrCreate(
            ['email' => 'client@demo.com'],
            [
                'name' => 'Demo Customer Corp',
                'phone' => '09123456789',
                'address' => '123 Business Street, Makati City',
                'is_active' => true,
                'created_by' => $systemUser?->id ?? 1,
            ]
        );

        $user = User::firstOrCreate(
            ['email' => 'client@demo.com'],
            [
                'name' => 'Demo Client User',
                'password' => Hash::make('password'),
                'client_id' => $customer->id,
            ]
        );

        if (!$user->hasRole('client')) {
            $user->assignRole('client');
        }

        $this->command->info('  ✓ Client user created');
    }

    private function createFinishedGoods(): void
    {
        $items = [
            [
                'item_code' => 'FG-001',
                'description' => 'Plastic Container 500ml',
                'unit_of_measure' => 'pcs',
            ],
            [
                'item_code' => 'FG-002',
                'description' => 'Plastic Container 1L',
                'unit_of_measure' => 'pcs',
            ],
            [
                'item_code' => 'FG-003',
                'description' => 'Bottle Cap Standard',
                'unit_of_measure' => 'pcs',
            ],
            [
                'item_code' => 'FG-004',
                'description' => 'Custom Molded Part A',
                'unit_of_measure' => 'pcs',
            ],
        ];

        $category = \App\Domains\Inventory\Models\ItemCategory::first();
        $categoryId = $category?->id ?? 1;
        
        foreach ($items as $itemData) {
            ItemMaster::firstOrCreate(
                ['item_code' => $itemData['item_code']],
                [
                    'name' => $itemData['description'],
                    'description' => $itemData['description'],
                    'unit_of_measure' => $itemData['unit_of_measure'],
                    'type' => 'finished_goods',
                    'is_active' => true,
                    'category_id' => $categoryId,
                ]
            );
        }

        $this->command->info('  ✓ ' . count($items) . ' finished goods items created');
    }

    private function createSalesUser(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'sales@demo.com'],
            [
                'name' => 'Demo Sales Officer',
                'password' => Hash::make('password'),
            ]
        );

        if (!$user->hasRole('officer')) {
            $user->assignRole('officer');
        }

        // Note: Permissions should be seeded via RolePermissionSeeder
        // This user gets permissions through the 'officer' role

        $this->command->info('  ✓ Sales user created');
    }
}
