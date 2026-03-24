<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comprehensive Test Data Seeder.
 *
 * Seeds realistic data across all major modules so every feature can be tested
 * immediately after `php artisan migrate:fresh --seed`.
 *
 * Covers:
 *  - Inventory: categories, items, warehouse locations, stock balances, stock ledger
 *  - Vendors: accredited vendors with catalog items
 *  - Customers: active customers with credit limits
 *  - Procurement: PRs (all statuses) + POs + GR draft
 *  - Production: BOM + production orders (various statuses)
 *  - QC: inspection templates + inspections (various stages/statuses) + NCRs
 *  - Maintenance: equipment + PM schedules + work orders
 *  - Delivery: vehicles
 *  - Fixed Assets: categories + assets with depreciation
 *  - Banking: bank accounts
 */
class ComprehensiveTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting Comprehensive Test Data Seeding...');

        $admin = User::where('email', 'admin@ogamierp.local')->first() ?? User::first();

        if (! $admin) {
            $this->command->warn('  No admin user found, skipping test data seeding.');
            return;
        }

        $this->seedWarehouseLocations($admin);
        $this->seedItemCategories($admin);
        $this->seedItemMasters($admin);
        $this->seedVendors($admin);
        $this->seedCustomers($admin);
        $this->seedDeliveryData($admin);
        $this->seedBankAccounts($admin);

        $this->command->info('✓ Comprehensive Test Data Seeding Complete!');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INVENTORY
    // ═══════════════════════════════════════════════════════════════════════

    private function seedWarehouseLocations(User $user): void
    {
        $this->command->info('  → Warehouse locations...');
        $locations = [
            ['code' => 'WH-MAIN-01', 'name' => 'Main Warehouse — Zone A', 'zone' => 'A', 'bin' => '01'],
            ['code' => 'WH-MAIN-02', 'name' => 'Main Warehouse — Zone B', 'zone' => 'B', 'bin' => '01'],
            ['code' => 'WH-RM-01',   'name' => 'Raw Materials Storage',   'zone' => 'RM', 'bin' => '01'],
            ['code' => 'WH-FG-01',   'name' => 'Finished Goods Storage',  'zone' => 'FG', 'bin' => '01'],
            ['code' => 'WH-QA-01',   'name' => 'QA Hold Area',            'zone' => 'QA', 'bin' => '01'],
        ];
        foreach ($locations as $loc) {
            DB::table('warehouse_locations')->insertOrIgnore([
                'code'       => $loc['code'],
                'name'       => $loc['name'],
                'zone'       => $loc['zone'],
                'bin'        => $loc['bin'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedItemCategories(User $user): void
    {
        $this->command->info('  → Item categories...');
        $categories = [
            ['code' => 'RAW-MAT',  'name' => 'Raw Materials'],
            ['code' => 'FIN-GOOD', 'name' => 'Finished Goods'],
            ['code' => 'SPARE-PT', 'name' => 'Spare Parts'],
            ['code' => 'CONSUMAB', 'name' => 'Consumables'],
            ['code' => 'PACKAGING','name' => 'Packaging Materials'],
        ];
        foreach ($categories as $cat) {
            DB::table('item_categories')->insertOrIgnore([
                'code'       => $cat['code'],
                'name'       => $cat['name'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedItemMasters(User $user): void
    {
        $this->command->info('  → Item masters + stock balances...');

        $rmCat  = DB::table('item_categories')->where('code', 'RAW-MAT')->value('id');
        $fgCat  = DB::table('item_categories')->where('code', 'FIN-GOOD')->value('id');
        $spCat  = DB::table('item_categories')->where('code', 'SPARE-PT')->value('id');
        $conCat = DB::table('item_categories')->where('code', 'CONSUMAB')->value('id');
        $pkCat  = DB::table('item_categories')->where('code', 'PACKAGING')->value('id');

        $items = [
            // Raw Materials
            ['code' => 'RM-PE-001',  'name' => 'Polyethylene Resin',      'uom' => 'kg',  'type' => 'raw_material',  'cat' => $rmCat,  'reorder' => 500,  'stock' => 1200],
            ['code' => 'RM-PP-001',  'name' => 'Polypropylene Resin',     'uom' => 'kg',  'type' => 'raw_material',  'cat' => $rmCat,  'reorder' => 300,  'stock' => 850],
            ['code' => 'RM-MB-001',  'name' => 'Color Masterbatch Black', 'uom' => 'kg',  'type' => 'raw_material',  'cat' => $rmCat,  'reorder' => 50,   'stock' => 80],
            ['code' => 'RM-MB-002',  'name' => 'Color Masterbatch White', 'uom' => 'kg',  'type' => 'raw_material',  'cat' => $rmCat,  'reorder' => 50,   'stock' => 40],
            // Finished Goods
            ['code' => 'FG-CONT-1L', 'name' => 'Plastic Container 1L',   'uom' => 'pcs', 'type' => 'finished_good', 'cat' => $fgCat,  'reorder' => 500,  'stock' => 2400],
            ['code' => 'FG-CONT-5L', 'name' => 'Plastic Container 5L',   'uom' => 'pcs', 'type' => 'finished_good', 'cat' => $fgCat,  'reorder' => 200,  'stock' => 600],
            ['code' => 'FG-CAP-001', 'name' => 'Plastic Cap Standard',   'uom' => 'pcs', 'type' => 'finished_good', 'cat' => $fgCat,  'reorder' => 1000, 'stock' => 450],
            // Spare Parts (low stock to trigger reorder alert)
            ['code' => 'SP-SCREW-01','name' => 'Injection Screw M12',    'uom' => 'pcs', 'type' => 'spare_part',    'cat' => $spCat,  'reorder' => 5,    'stock' => 3],
            ['code' => 'SP-BELT-01', 'name' => 'Drive Belt 50mm',        'uom' => 'pcs', 'type' => 'spare_part',    'cat' => $spCat,  'reorder' => 4,    'stock' => 2],
            // Consumables
            ['code' => 'CON-OIL-01', 'name' => 'Hydraulic Oil 46',       'uom' => 'L',   'type' => 'consumable',    'cat' => $conCat, 'reorder' => 20,   'stock' => 55],
            // Packaging
            ['code' => 'PKG-BOX-S',  'name' => 'Carton Box Small',       'uom' => 'pcs', 'type' => 'raw_material',  'cat' => $pkCat,  'reorder' => 100,  'stock' => 320],
        ];

        $location = DB::table('warehouse_locations')->where('code', 'WH-RM-01')->value('id')
            ?? DB::table('warehouse_locations')->value('id');

        foreach ($items as $item) {
            $exists = DB::table('item_masters')->where('item_code', $item['code'])->exists();
            if ($exists) {
                continue;
            }

            $itemId = DB::table('item_masters')->insertGetId([
                'ulid'            => (string) Str::ulid(),
                'item_code'       => $item['code'],
                'category_id'     => $item['cat'],
                'name'            => $item['name'],
                'unit_of_measure' => $item['uom'],
                'type'            => $item['type'],
                'reorder_point'   => $item['reorder'],
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Seed initial stock balance
            if ($location && $item['stock'] > 0) {
                DB::table('stock_balances')->insertOrIgnore([
                    'item_id'          => $itemId,
                    'location_id'      => $location,
                    'quantity_on_hand' => $item['stock'],
                    'updated_at'       => now(),
                ]);

                // Seed stock ledger entry
                DB::table('stock_ledger')->insertOrIgnore([
                    'item_id'          => $itemId,
                    'location_id'      => $location,
                    'transaction_type' => 'adjustment',
                    'quantity'         => $item['stock'],
                    'balance_after'    => $item['stock'],
                    'remarks'          => 'Initial stock — seed data (' . $item['code'] . ')',
                    'created_by_id'    => $user->id,
                    'created_at'       => now(),
                ]);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // VENDORS
    // ═══════════════════════════════════════════════════════════════════════

    private function seedVendors(User $user): void
    {
        $this->command->info('  → Vendors + vendor catalog items...');

        $vendors = [
            [
                'name'               => 'Petrochem Supplies Inc.',
                'tin'                => '123-456-789-000',
                'accreditation_status' => 'accredited',
                'email'              => 'orders@petrochem.ph',
                'phone'              => '02-8123-4567',
                'address'            => '100 Industrial Ave, Laguna',
                'contact_person'     => 'Juan dela Cruz',
                'payment_terms'      => 'Net 30',
                'bank_name'          => 'BDO',
                'bank_account_no'    => '00123456789',
                'bank_account_name'  => 'Petrochem Supplies Inc.',
                'lead_time_days'     => 7,
                'items'              => [
                    ['code' => 'PC-PE-001', 'name' => 'Polyethylene Resin',      'uom' => 'kg',  'price' => 8500],
                    ['code' => 'PC-PP-001', 'name' => 'Polypropylene Resin',     'uom' => 'kg',  'price' => 9200],
                    ['code' => 'PC-MB-001', 'name' => 'Color Masterbatch Black', 'uom' => 'kg',  'price' => 15000],
                ],
            ],
            [
                'name'               => 'Packaging Solutions Corp.',
                'tin'                => '987-654-321-000',
                'accreditation_status' => 'accredited',
                'email'              => 'sales@pkgsolutions.ph',
                'phone'              => '02-8987-6543',
                'address'            => '55 Packing Lane, Bulacan',
                'contact_person'     => 'Maria Reyes',
                'payment_terms'      => 'Net 15',
                'bank_name'          => 'Metrobank',
                'bank_account_no'    => '003456789012',
                'bank_account_name'  => 'Packaging Solutions Corp.',
                'lead_time_days'     => 5,
                'items'              => [
                    ['code' => 'PKG-BOX-S',  'name' => 'Carton Box Small',   'uom' => 'pcs', 'price' => 1200],
                    ['code' => 'PKG-BOX-M',  'name' => 'Carton Box Medium',  'uom' => 'pcs', 'price' => 1800],
                    ['code' => 'PKG-TAPE-01','name' => 'Packing Tape 48mm',  'uom' => 'rolls','price' => 450],
                ],
            ],
            [
                'name'               => 'Industrial Parts Trading',
                'tin'                => '456-789-123-000',
                'accreditation_status' => 'accredited',
                'email'              => 'parts@indiparts.ph',
                'phone'              => '02-8456-7891',
                'address'            => '22 Spare Parts St., Pampanga',
                'contact_person'     => 'Pedro Santos',
                'payment_terms'      => 'COD',
                'bank_name'          => 'PNB',
                'bank_account_no'    => '006789012345',
                'bank_account_name'  => 'Industrial Parts Trading',
                'lead_time_days'     => 14,
                'items'              => [
                    ['code' => 'SP-SCREW-01', 'name' => 'Injection Screw M12', 'uom' => 'pcs', 'price' => 350000],
                    ['code' => 'SP-BELT-01',  'name' => 'Drive Belt 50mm',     'uom' => 'pcs', 'price' => 85000],
                    ['code' => 'SP-SEAL-01',  'name' => 'Hydraulic Seal Kit',  'uom' => 'sets','price' => 220000],
                ],
            ],
            [
                'name'               => 'ChemLube Philippines',
                'tin'                => '321-654-987-000',
                'accreditation_status' => 'accredited',
                'email'              => 'sales@chemlube.ph',
                'phone'              => '02-8321-6549',
                'address'            => '8 Lubricants Ave., Batangas',
                'contact_person'     => 'Ana Garcia',
                'payment_terms'      => 'Net 30',
                'bank_name'          => 'Security Bank',
                'bank_account_no'    => '009876543210',
                'bank_account_name'  => 'ChemLube Philippines',
                'lead_time_days'     => 10,
                'items'              => [
                    ['code' => 'OIL-HYD-46', 'name' => 'Hydraulic Oil 46', 'uom' => 'L', 'price' => 28000],
                    ['code' => 'OIL-GR-001', 'name' => 'Machine Grease',   'uom' => 'kg', 'price' => 45000],
                ],
            ],
        ];

        foreach ($vendors as $v) {
            if (DB::table('vendors')->where('tin', $v['tin'])->exists()) {
                continue;
            }

            $vendorId = DB::table('vendors')->insertGetId([
                'name'                 => $v['name'],
                'tin'                  => $v['tin'],
                'accreditation_status' => $v['accreditation_status'],
                'email'                => $v['email'],
                'phone'                => $v['phone'],
                'address'              => $v['address'],
                'contact_person'       => $v['contact_person'],
                'payment_terms'        => $v['payment_terms'],
                'bank_name'            => $v['bank_name'],
                'bank_account_no'      => $v['bank_account_no'],
                'bank_account_name'    => $v['bank_account_name'],
                'lead_time_days'       => $v['lead_time_days'],
                'is_active'            => true,
                'created_by'           => $user->id,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            foreach ($v['items'] as $vi) {
                DB::table('vendor_items')->insertOrIgnore([
                    'ulid'            => (string) Str::ulid(),
                    'vendor_id'       => $vendorId,
                    'item_code'       => $vi['code'],
                    'item_name'       => $vi['name'],
                    'unit_of_measure' => $vi['uom'],
                    'unit_price'      => $vi['price'],
                    'is_active'       => true,
                    'created_by_id'   => $user->id,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CUSTOMERS
    // ═══════════════════════════════════════════════════════════════════════

    private function seedCustomers(User $user): void
    {
        $this->command->info('  → Customers...');

        $arAccountId = DB::table('chart_of_accounts')
            ->where('name', 'LIKE', '%Accounts Receivable%')
            ->value('id');

        $customers = [
            [
                'name'           => 'Mega Foods Corporation',
                'tin'            => '200-001-001-000',
                'email'          => 'procurement@megafoods.ph',
                'phone'          => '02-8200-0011',
                'contact_person' => 'Luisa Mendoza',
                'address'        => '1 EDSA cor. Shaw Blvd., Mandaluyong',
                'billing_address'=> '1 EDSA cor. Shaw Blvd., Mandaluyong City 1550',
                'credit_limit'   => 5000000,
            ],
            [
                'name'           => 'Southern Packaging Traders',
                'tin'            => '200-002-002-000',
                'email'          => 'orders@spt.ph',
                'phone'          => '032-888-0022',
                'contact_person' => 'Roberto Tan',
                'address'        => '23 V. Rama Ave., Cebu City',
                'billing_address'=> '23 V. Rama Ave., Cebu City 6000',
                'credit_limit'   => 2000000,
            ],
            [
                'name'           => 'North Luzon Distributors Inc.',
                'tin'            => '200-003-003-000',
                'email'          => 'supply@nld.ph',
                'phone'          => '074-300-0033',
                'contact_person' => 'Carmen Villanueva',
                'address'        => '45 Session Rd., Baguio City',
                'billing_address'=> '45 Session Rd., Baguio City 2600',
                'credit_limit'   => 1500000,
            ],
        ];

        foreach ($customers as $c) {
            if (DB::table('customers')->where('tin', $c['tin'])->exists()) {
                continue;
            }

            DB::table('customers')->insert([
                'ulid'           => (string) Str::ulid(),
                'name'           => $c['name'],
                'tin'            => $c['tin'],
                'email'          => $c['email'],
                'phone'          => $c['phone'],
                'contact_person' => $c['contact_person'],
                'address'        => $c['address'],
                'billing_address'=> $c['billing_address'],
                'credit_limit'   => $c['credit_limit'],
                'is_active'      => true,
                'ar_account_id'  => $arAccountId,
                'created_by'     => $user->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PROCUREMENT
    // ═══════════════════════════════════════════════════════════════════════

    private function seedProcurementData(User $user): void
    {
        $this->command->info('  → Procurement (PRs + POs)...');

        $dept = DB::table('departments')->where('code', 'PROD')
            ->orWhere('code', 'MFGPROD')
            ->orWhere('name', 'LIKE', '%Production%')
            ->first()
            ?? DB::table('departments')->first();

        if (! $dept) {
            $this->command->warn('    No departments found, skipping procurement.');
            return;
        }

        $vendor = DB::table('vendors')->where('accreditation_status', 'accredited')->first();
        if (! $vendor) {
            $this->command->warn('    No accredited vendor found, skipping POs.');
        }

        // PR 1 — Draft (editable, not yet submitted)
        $this->createPr($user, $dept->id, 'PR-2026-0001', 'draft', [
            ['desc' => 'Polyethylene Resin',  'uom' => 'kg', 'qty' => 500, 'cost' => 8.50],
            ['desc' => 'Polypropylene Resin', 'uom' => 'kg', 'qty' => 300, 'cost' => 9.20],
        ], 'Monthly raw material replenishment');

        // PR 2 — Pending Review (submitted, waiting for purchasing officer)
        $pr2 = $this->createPr($user, $dept->id, 'PR-2026-0002', 'pending_review', [
            ['desc' => 'Color Masterbatch Black', 'uom' => 'kg', 'qty' => 50, 'cost' => 15.00],
        ], 'Urgent: masterbatch stock critically low', [
            'submitted_by_id' => $user->id,
            'submitted_at'    => now()->subDays(2),
        ]);

        // PR 3 — Reviewed (approved by purchasing officer, waiting for budget check)
        $pr3 = $this->createPr($user, $dept->id, 'PR-2026-0003', 'reviewed', [
            ['desc' => 'Injection Screw M12', 'uom' => 'pcs', 'qty' => 4,  'cost' => 3500.00],
            ['desc' => 'Drive Belt 50mm',     'uom' => 'pcs', 'qty' => 4,  'cost' => 850.00],
        ], 'Spare parts for injection molding machines', [
            'submitted_by_id' => $user->id,
            'submitted_at'    => now()->subDays(5),
            'reviewed_by_id'  => $user->id,
            'reviewed_at'     => now()->subDays(3),
        ]);

        // PR 4 — Budget Verified (ready for VP approval)
        $pr4 = $this->createPr($user, $dept->id, 'PR-2026-0004', 'budget_verified', [
            ['desc' => 'Hydraulic Oil 46', 'uom' => 'L', 'qty' => 100, 'cost' => 280.00],
            ['desc' => 'Machine Grease',   'uom' => 'kg','qty' => 20,  'cost' => 450.00],
        ], 'Quarterly lubricant replenishment', [
            'submitted_by_id' => $user->id,
            'submitted_at'    => now()->subDays(8),
            'reviewed_by_id'  => $user->id,
            'reviewed_at'     => now()->subDays(6),
        ]);

        // PR 5 — Approved → will have a PO
        $pr5 = $this->createPr($user, $dept->id, 'PR-2026-0005', 'approved', [
            ['desc' => 'Carton Box Small', 'uom' => 'pcs', 'qty' => 500, 'cost' => 12.00],
            ['desc' => 'Packing Tape 48mm','uom' => 'rolls','qty' => 50, 'cost' => 4.50],
        ], 'Packaging materials for Q1 production', [
            'submitted_by_id'  => $user->id,
            'submitted_at'     => now()->subDays(12),
            'reviewed_by_id'   => $user->id,
            'reviewed_at'      => now()->subDays(10),
            'vp_approved_by_id'=> $user->id,
            'vp_approved_at'   => now()->subDays(8),
        ]);

        // PR 6 — Converted to PO
        $pr6 = $this->createPr($user, $dept->id, 'PR-2026-0006', 'converted_to_po', [
            ['desc' => 'Polyethylene Resin', 'uom' => 'kg', 'qty' => 1000, 'cost' => 8.50],
        ], 'Large resin order for production run', [
            'submitted_by_id'  => $user->id,
            'submitted_at'     => now()->subDays(20),
            'reviewed_by_id'   => $user->id,
            'reviewed_at'      => now()->subDays(18),
            'vp_approved_by_id'=> $user->id,
            'vp_approved_at'   => now()->subDays(15),
        ]);

        // Create POs for the approved PRs (5 and 6)
        if ($vendor) {
            $this->createPo($user, $pr5, $vendor->id, 'PO-2026-0001', 'draft', [
                ['desc' => 'Carton Box Small',  'uom' => 'pcs',  'qty' => 500,  'cost' => 12.00],
                ['desc' => 'Packing Tape 48mm', 'uom' => 'rolls','qty' => 50,   'cost' => 4.50],
            ]);

            $this->createPo($user, $pr6, $vendor->id, 'PO-2026-0002', 'sent', [
                ['desc' => 'Polyethylene Resin', 'uom' => 'kg', 'qty' => 1000, 'cost' => 8.50],
            ], ['sent_at' => now()->subDays(10)]);
        }
    }

    private function createPr(User $user, int $deptId, string $ref, string $status, array $items, string $justification, array $extra = []): ?object
    {
        if (DB::table('purchase_requests')->where('pr_reference', $ref)->exists()) {
            return DB::table('purchase_requests')->where('pr_reference', $ref)->first();
        }

        $prId = DB::table('purchase_requests')->insertGetId(array_merge([
            'ulid'            => (string) Str::ulid(),
            'pr_reference'    => $ref,
            'department_id'   => $deptId,
            'requested_by_id' => $user->id,
            'urgency'         => 'normal',
            'justification'   => $justification,
            'status'          => $status,
            'created_at'      => now()->subDays(rand(1, 25)),
            'updated_at'      => now(),
        ], $extra));

        foreach ($items as $i => $item) {
            DB::table('purchase_request_items')->insert([
                'purchase_request_id' => $prId,
                'item_description'    => $item['desc'],
                'unit_of_measure'     => $item['uom'],
                'quantity'            => $item['qty'],
                'estimated_unit_cost' => $item['cost'],
                'line_order'          => $i + 1,
                'created_at'          => now(),
            ]);
        }

        return DB::table('purchase_requests')->where('id', $prId)->first();
    }

    private function createPo(User $user, ?object $pr, int $vendorId, string $ref, string $status, array $items, array $extra = []): void
    {
        if (! $pr || DB::table('purchase_orders')->where('po_reference', $ref)->exists()) {
            return;
        }

        $deliveryDate = now()->addDays(14);
        $poDate       = now()->subDays(5);

        $poId = DB::table('purchase_orders')->insertGetId(array_merge([
            'ulid'               => (string) Str::ulid(),
            'po_reference'       => $ref,
            'purchase_request_id'=> $pr->id,
            'vendor_id'          => $vendorId,
            'po_date'            => $poDate->toDateString(),
            'delivery_date'      => $deliveryDate->toDateString(),
            'payment_terms'      => 'Net 30',
            'delivery_address'   => 'Company Warehouse, Plant Road, Laguna',
            'status'             => $status,
            'total_po_amount'    => array_sum(array_map(fn($i) => $i['qty'] * $i['cost'], $items)),
            'created_by_id'      => $user->id,
            'created_at'         => now()->subDays(5),
            'updated_at'         => now(),
        ], $extra));

        foreach ($items as $i => $item) {
            DB::table('purchase_order_items')->insert([
                'purchase_order_id' => $poId,
                'item_description'  => $item['desc'],
                'unit_of_measure'   => $item['uom'],
                'quantity_ordered'  => $item['qty'],
                'agreed_unit_cost'  => $item['cost'],
                'quantity_received' => 0,
                'line_order'        => $i + 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        // Update PR to link converted PO
        DB::table('purchase_requests')
            ->where('id', $pr->id)
            ->update(['converted_to_po_id' => $poId, 'converted_at' => now(), 'updated_at' => now()]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRODUCTION
    // ═══════════════════════════════════════════════════════════════════════

    private function seedProductionData(User $user): void
    {
        $this->command->info('  → Production (BOM + work orders)...');

        $fgItem1 = DB::table('item_masters')->where('item_code', 'FG-CONT-1L')->first();
        $fgItem2 = DB::table('item_masters')->where('item_code', 'FG-CONT-5L')->first();
        $rm1     = DB::table('item_masters')->where('item_code', 'RM-PE-001')->first();
        $rm2     = DB::table('item_masters')->where('item_code', 'RM-PP-001')->first();
        $rm3     = DB::table('item_masters')->where('item_code', 'RM-MB-001')->first();

        if (! $fgItem1 || ! $rm1) {
            $this->command->warn('    Item masters not ready, skipping production.');
            return;
        }

        // BOM for 1L container
        $bom1Id = DB::table('bill_of_materials')->insertGetId([
            'ulid'            => (string) Str::ulid(),
            'product_item_id' => $fgItem1->id,
            'version'         => '1.0',
            'is_active'       => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        DB::table('bom_components')->insert([
            ['bom_id' => $bom1Id, 'component_item_id' => $rm1->id, 'qty_per_unit' => 0.080, 'unit_of_measure' => 'kg', 'scrap_factor_pct' => 2.0],
            ['bom_id' => $bom1Id, 'component_item_id' => $rm3->id, 'qty_per_unit' => 0.002, 'unit_of_measure' => 'kg', 'scrap_factor_pct' => 1.0],
        ]);

        // BOM for 5L container
        if ($fgItem2 && $rm2) {
            $bom2Id = DB::table('bill_of_materials')->insertGetId([
                'ulid'            => (string) Str::ulid(),
                'product_item_id' => $fgItem2->id,
                'version'         => '1.0',
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            DB::table('bom_components')->insert([
                ['bom_id' => $bom2Id, 'component_item_id' => $rm2->id, 'qty_per_unit' => 0.350, 'unit_of_measure' => 'kg', 'scrap_factor_pct' => 2.0],
                ['bom_id' => $bom2Id, 'component_item_id' => $rm3->id, 'qty_per_unit' => 0.008, 'unit_of_measure' => 'kg', 'scrap_factor_pct' => 1.0],
            ]);
        }

        // Production Orders in various states
        $orders = [
            [
                'ref'    => 'WO-2026-0001', 'bom_id' => $bom1Id, 'product_id' => $fgItem1->id,
                'status' => 'draft',        'qty_req' => 5000, 'qty_prod' => 0,
                'start'  => now()->addDays(3)->toDateString(), 'end' => now()->addDays(7)->toDateString(),
            ],
            [
                'ref'    => 'WO-2026-0002', 'bom_id' => $bom1Id, 'product_id' => $fgItem1->id,
                'status' => 'released',     'qty_req' => 3000, 'qty_prod' => 0,
                'start'  => now()->toDateString(), 'end' => now()->addDays(4)->toDateString(),
            ],
            [
                'ref'    => 'WO-2026-0003', 'bom_id' => $bom1Id, 'product_id' => $fgItem1->id,
                'status' => 'in_progress',  'qty_req' => 8000, 'qty_prod' => 4200,
                'start'  => now()->subDays(3)->toDateString(), 'end' => now()->addDays(2)->toDateString(),
            ],
            [
                'ref'    => 'WO-2026-0004', 'bom_id' => $bom1Id, 'product_id' => $fgItem1->id,
                'status' => 'completed',    'qty_req' => 10000, 'qty_prod' => 10000,
                'start'  => now()->subDays(15)->toDateString(), 'end' => now()->subDays(10)->toDateString(),
            ],
            [
                'ref'    => 'WO-2026-0005', 'bom_id' => $bom2Id ?? $bom1Id, 'product_id' => $fgItem2->id ?? $fgItem1->id,
                'status' => 'in_progress',  'qty_req' => 2000, 'qty_prod' => 750,
                'start'  => now()->subDays(2)->toDateString(), 'end' => now()->addDays(5)->toDateString(),
            ],
        ];

        foreach ($orders as $wo) {
            if (DB::table('production_orders')->where('po_reference', $wo['ref'])->exists()) {
                continue;
            }
            DB::table('production_orders')->insert([
                'ulid'             => (string) Str::ulid(),
                'po_reference'     => $wo['ref'],
                'product_item_id'  => $wo['product_id'],
                'bom_id'           => $wo['bom_id'],
                'qty_required'     => $wo['qty_req'],
                'qty_produced'     => $wo['qty_prod'],
                'target_start_date'=> $wo['start'],
                'target_end_date'  => $wo['end'],
                'status'           => $wo['status'],
                'created_by_id'    => $user->id,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // QC
    // ═══════════════════════════════════════════════════════════════════════

    private function seedQCData(User $user): void
    {
        $this->command->info('  → QC (templates + inspections + NCRs)...');

        // Inspection Template — IQC
        DB::table('inspection_templates')->insertOrIgnore([
            'ulid'          => (string) Str::ulid(),
            'name'          => 'Raw Material Incoming Inspection',
            'stage'         => 'iqc',
            'description'   => 'Standard IQC checklist for raw material batches',
            'is_active'     => true,
            'created_by_id' => $user->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $iqcTemplate = DB::table('inspection_templates')->where('stage', 'iqc')->first();

        if ($iqcTemplate && DB::table('inspection_template_items')->where('inspection_template_id', $iqcTemplate->id)->doesntExist()) {
            DB::table('inspection_template_items')->insert([
                ['inspection_template_id' => $iqcTemplate->id, 'criterion' => 'Visual appearance / no contamination', 'method' => 'Visual',         'acceptable_range' => 'No visible defects',    'sort_order' => 1],
                ['inspection_template_id' => $iqcTemplate->id, 'criterion' => 'Moisture content',                     'method' => 'Moisture meter', 'acceptable_range' => '< 0.05%',               'sort_order' => 2],
                ['inspection_template_id' => $iqcTemplate->id, 'criterion' => 'Melt Flow Index',                      'method' => 'MFI tester',     'acceptable_range' => '4–6 g/10min',           'sort_order' => 3],
                ['inspection_template_id' => $iqcTemplate->id, 'criterion' => 'Particle size uniformity',             'method' => 'Sieve analysis', 'acceptable_range' => '95% within 3–5mm',      'sort_order' => 4],
            ]);
        }

        // Inspection Template — IPQC
        DB::table('inspection_templates')->insertOrIgnore([
            'ulid'          => (string) Str::ulid(),
            'name'          => 'In-Process Dimensional Check',
            'stage'         => 'ipqc',
            'description'   => 'In-process dimensional inspection during injection molding',
            'is_active'     => true,
            'created_by_id' => $user->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $ipqcTemplate = DB::table('inspection_templates')->where('stage', 'ipqc')->first();

        if ($ipqcTemplate && DB::table('inspection_template_items')->where('inspection_template_id', $ipqcTemplate->id)->doesntExist()) {
            DB::table('inspection_template_items')->insert([
                ['inspection_template_id' => $ipqcTemplate->id, 'criterion' => 'Wall thickness', 'method' => 'Caliper',  'acceptable_range' => '2.0 ± 0.2 mm', 'sort_order' => 1],
                ['inspection_template_id' => $ipqcTemplate->id, 'criterion' => 'Lid fit / snap', 'method' => 'Manual',   'acceptable_range' => 'Secure snap, no gap', 'sort_order' => 2],
                ['inspection_template_id' => $ipqcTemplate->id, 'criterion' => 'Surface finish',  'method' => 'Visual',   'acceptable_range' => 'No sink marks, weld lines', 'sort_order' => 3],
                ['inspection_template_id' => $ipqcTemplate->id, 'criterion' => 'Container capacity','method' => 'Water fill','acceptable_range' => '1000 ± 10 ml',  'sort_order' => 4],
            ]);
        }

        // Inspections
        $rm1 = DB::table('item_masters')->where('item_code', 'RM-PE-001')->first();
        $fg1 = DB::table('item_masters')->where('item_code', 'FG-CONT-1L')->first();

        $inspections = [
            [
                'stage'   => 'iqc', 'status' => 'passed', 'item' => $rm1,
                'qty_i'   => 500, 'qty_p' => 498, 'qty_f' => 2,
                'date'    => now()->subDays(14)->toDateString(),
                'remarks' => 'Batch RM-2026-001. Two bags had minor moisture spots on exterior packaging. Product inside acceptable.',
                'template'=> $iqcTemplate,
            ],
            [
                'stage'   => 'iqc', 'status' => 'failed', 'item' => $rm1,
                'qty_i'   => 300, 'qty_p' => 180, 'qty_f' => 120,
                'date'    => now()->subDays(7)->toDateString(),
                'remarks' => 'Batch RM-2026-002. MFI out of spec — 8.2 g/10min vs target 4–6. Raised NCR.',
                'template'=> $iqcTemplate,
            ],
            [
                'stage'   => 'ipqc', 'status' => 'passed', 'item' => $fg1,
                'qty_i'   => 200, 'qty_p' => 200, 'qty_f' => 0,
                'date'    => now()->subDays(3)->toDateString(),
                'remarks' => 'All units within spec. WO-2026-0003 midpoint check.',
                'template'=> $ipqcTemplate,
            ],
            [
                'stage'   => 'oqc', 'status' => 'passed', 'item' => $fg1,
                'qty_i'   => 10000, 'qty_p' => 9985, 'qty_f' => 15,
                'date'    => now()->subDays(10)->toDateString(),
                'remarks' => 'Final OQC for WO-2026-0004. 15 units rejected for sink marks. 9,985 units cleared for shipment.',
                'template'=> null,
            ],
            [
                'stage'   => 'iqc', 'status' => 'open', 'item' => $rm1,
                'qty_i'   => 1000, 'qty_p' => 0, 'qty_f' => 0,
                'date'    => now()->toDateString(),
                'remarks' => 'Batch RM-2026-003. Inspection in progress.',
                'template'=> $iqcTemplate,
            ],
            [
                'stage'   => 'ipqc', 'status' => 'on_hold', 'item' => $fg1,
                'qty_i'   => 100, 'qty_p' => 60, 'qty_f' => 40,
                'date'    => now()->subDays(1)->toDateString(),
                'remarks' => 'Higher-than-expected failure rate on lid snap. Production paused pending root cause.',
                'template'=> $ipqcTemplate,
            ],
        ];

        $inspectionIds = [];
        foreach ($inspections as $insp) {
            if (! $insp['item']) {
                continue;
            }
            $id = DB::table('inspections')->insertGetId([
                'ulid'              => (string) Str::ulid(),
                'stage'             => $insp['stage'],
                'status'            => $insp['status'],
                'item_master_id'    => $insp['item']->id,
                'inspection_template_id' => $insp['template']?->id,
                'qty_inspected'     => $insp['qty_i'],
                'qty_passed'        => $insp['qty_p'],
                'qty_failed'        => $insp['qty_f'],
                'inspection_date'   => $insp['date'],
                'inspector_id'      => $user->id,
                'remarks'           => $insp['remarks'],
                'created_by_id'     => $user->id,
                'created_at'        => now()->subDays(rand(0, 14)),
                'updated_at'        => now(),
            ]);
            $inspectionIds[$insp['status']] = $id;

            // Seed result rows for non-open inspections with templates
            if ($insp['status'] !== 'open' && $insp['template']) {
                $templateItems = DB::table('inspection_template_items')
                    ->where('inspection_template_id', $insp['template']->id)
                    ->get();

                $isPass = $insp['status'] === 'passed';
                foreach ($templateItems as $ti) {
                    DB::table('inspection_results')->insert([
                        'inspection_id'              => $id,
                        'inspection_template_item_id'=> $ti->id,
                        'criterion'                  => $ti->criterion,
                        'actual_value'               => $isPass ? $ti->acceptable_range : 'OUT OF SPEC',
                        'is_conforming'              => $isPass,
                        'remarks'                    => $isPass ? null : 'Value outside acceptable range',
                        'created_at'                 => now(),
                    ]);
                }
            }
        }

        // NCR for the failed inspection
        $failedInspId = $inspectionIds['failed'] ?? null;
        if ($failedInspId && DB::table('non_conformance_reports')->where('inspection_id', $failedInspId)->doesntExist()) {
            $ncrId = DB::table('non_conformance_reports')->insertGetId([
                'ulid'         => (string) Str::ulid(),
                'inspection_id'=> $failedInspId,
                'title'        => 'PE Resin Batch RM-2026-002: MFI Out of Specification',
                'description'  => 'Melt Flow Index measured at 8.2 g/10min exceeds upper limit of 6.0 g/10min. 120 out of 300 kg affected. Batch is on hold pending vendor corrective action.',
                'severity'     => 'major',
                'status'       => 'capa_issued',
                'raised_by_id' => $user->id,
                'created_at'   => now()->subDays(6),
                'updated_at'   => now(),
            ]);

            // CAPA Actions
            DB::table('capa_actions')->insert([
                [
                    'ulid'            => (string) Str::ulid(),
                    'ncr_id'          => $ncrId,
                    'type'            => 'corrective',
                    'description'     => 'Return non-conforming batch to supplier. Request replacement with certificate of analysis.',
                    'assigned_to_id'  => $user->id,
                    'due_date'        => now()->addDays(7)->toDateString(),
                    'status'          => 'in_progress',
                    'created_by_id'   => $user->id,
                    'created_at'      => now()->subDays(5),
                    'updated_at'      => now(),
                ],
                [
                    'ulid'            => (string) Str::ulid(),
                    'ncr_id'          => $ncrId,
                    'type'            => 'preventive',
                    'description'     => 'Update incoming inspection procedure to always test MFI on 10% sample minimum. Add MFI tester calibration to monthly PM schedule.',
                    'assigned_to_id'  => $user->id,
                    'due_date'        => now()->addDays(14)->toDateString(),
                    'status'          => 'open',
                    'created_by_id'   => $user->id,
                    'created_at'      => now()->subDays(5),
                    'updated_at'      => now(),
                ],
            ]);
        }

        // NCR for the on_hold inspection
        $onHoldInspId = $inspectionIds['on_hold'] ?? null;
        if ($onHoldInspId && DB::table('non_conformance_reports')->where('inspection_id', $onHoldInspId)->doesntExist()) {
            DB::table('non_conformance_reports')->insert([
                'ulid'         => (string) Str::ulid(),
                'inspection_id'=> $onHoldInspId,
                'title'        => '1L Container Lid Snap Failure Rate Exceeds 40%',
                'description'  => 'IPQC check on current production run found 40% lid snap failure. Root cause under investigation — suspected mold temperature deviation.',
                'severity'     => 'critical',
                'status'       => 'under_review',
                'raised_by_id' => $user->id,
                'created_at'   => now()->subDays(1),
                'updated_at'   => now(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MAINTENANCE
    // ═══════════════════════════════════════════════════════════════════════

    private function seedMaintenanceData(User $user): void
    {
        $this->command->info('  → Maintenance (equipment + work orders)...');

        $equipment = [
            ['code' => 'EQ-IMM-001', 'name' => 'Injection Molding Machine #1', 'category' => 'Production',  'manufacturer' => 'Toshiba',    'location' => 'Production Hall A', 'status' => 'operational'],
            ['code' => 'EQ-IMM-002', 'name' => 'Injection Molding Machine #2', 'category' => 'Production',  'manufacturer' => 'Mitsubishi', 'location' => 'Production Hall A', 'status' => 'operational'],
            ['code' => 'EQ-AIR-001', 'name' => 'Air Compressor 75kW',          'category' => 'Utility',     'manufacturer' => 'Atlas Copco','location' => 'Utility Room',      'status' => 'operational'],
            ['code' => 'EQ-GEN-001', 'name' => 'Generator Set 200kVA',         'category' => 'Utility',     'manufacturer' => 'Cummins',    'location' => 'Generator Room',    'status' => 'operational'],
            ['code' => 'EQ-CHL-001', 'name' => 'Chiller Unit',                  'category' => 'Production',  'manufacturer' => 'Carrier',    'location' => 'Chiller Room',      'status' => 'under_maintenance'],
        ];

        $eqIds = [];
        foreach ($equipment as $eq) {
            if (DB::table('equipment')->where('equipment_code', $eq['code'])->exists()) {
                $eqIds[$eq['code']] = DB::table('equipment')->where('equipment_code', $eq['code'])->value('id');
                continue;
            }
            $id = DB::table('equipment')->insertGetId([
                'ulid'           => (string) Str::ulid(),
                'equipment_code' => $eq['code'],
                'name'           => $eq['name'],
                'category'       => $eq['category'],
                'manufacturer'   => $eq['manufacturer'],
                'location'       => $eq['location'],
                'status'         => $eq['status'],
                'is_active'      => true,
                'created_by_id'  => $user->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $eqIds[$eq['code']] = $id;
        }

        // PM Schedules
        if (isset($eqIds['EQ-IMM-001'])) {
            $pmItems = [
                ['eq' => 'EQ-IMM-001', 'task' => 'Monthly lubrication & grease points', 'freq' => 30,  'last' => now()->subDays(12)],
                ['eq' => 'EQ-IMM-001', 'task' => 'Hydraulic oil change',               'freq' => 90,  'last' => now()->subDays(45)],
                ['eq' => 'EQ-IMM-002', 'task' => 'Monthly lubrication & grease points', 'freq' => 30,  'last' => now()->subDays(28)],
                ['eq' => 'EQ-AIR-001', 'task' => 'Air filter replacement',              'freq' => 30,  'last' => now()->subDays(5)],
                ['eq' => 'EQ-AIR-001', 'task' => 'Oil separator element change',        'freq' => 180, 'last' => now()->subDays(160)],
                ['eq' => 'EQ-GEN-001', 'task' => 'Weekly run test (30 min)',            'freq' => 7,   'last' => now()->subDays(6)],
                ['eq' => 'EQ-GEN-001', 'task' => 'Engine oil & filter change',         'freq' => 90,  'last' => now()->subDays(95)],
            ];

            foreach ($pmItems as $pm) {
                if (! isset($eqIds[$pm['eq']])) {
                    continue;
                }
                DB::table('pm_schedules')->insertOrIgnore([
                    'ulid'          => (string) Str::ulid(),
                    'equipment_id'  => $eqIds[$pm['eq']],
                    'task_name'     => $pm['task'],
                    'frequency_days'=> $pm['freq'],
                    'last_done_on'  => $pm['last']->toDateString(),
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        // Work Orders
        $workOrders = [
            ['eq' => 'EQ-IMM-001', 'type' => 'preventive', 'priority' => 'normal', 'status' => 'open',       'title' => 'Monthly PM — IMM #1', 'date' => now()->addDays(3)->toDateString()],
            ['eq' => 'EQ-IMM-001', 'type' => 'corrective', 'priority' => 'high',   'status' => 'in_progress','title' => 'Hydraulic pressure drop — IMM #1', 'date' => now()->toDateString()],
            ['eq' => 'EQ-CHL-001', 'type' => 'corrective', 'priority' => 'critical','status' => 'open',       'title' => 'Chiller cooling capacity degraded', 'date' => now()->toDateString()],
            ['eq' => 'EQ-AIR-001', 'type' => 'preventive', 'priority' => 'normal', 'status' => 'completed',  'title' => 'Air filter replacement — Compressor', 'date' => now()->subDays(5)->toDateString()],
            ['eq' => 'EQ-GEN-001', 'type' => 'preventive', 'priority' => 'normal', 'status' => 'open',       'title' => 'Generator oil change overdue', 'date' => now()->addDays(1)->toDateString()],
        ];

        foreach ($workOrders as $wo) {
            if (! isset($eqIds[$wo['eq']])) {
                continue;
            }
            DB::table('maintenance_work_orders')->insertOrIgnore([
                'ulid'           => (string) Str::ulid(),
                'equipment_id'   => $eqIds[$wo['eq']],
                'type'           => $wo['type'],
                'priority'       => $wo['priority'],
                'status'         => $wo['status'],
                'title'          => $wo['title'],
                'description'    => $wo['title'] . ' — seeded test data',
                'reported_by_id' => $user->id,
                'assigned_to_id' => $user->id,
                'scheduled_date' => $wo['date'],
                'created_by_id'  => $user->id,
                'created_at'     => now()->subDays(rand(0, 5)),
                'updated_at'     => now(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DELIVERY
    // ═══════════════════════════════════════════════════════════════════════

    private function seedDeliveryData(User $user): void
    {
        $this->command->info('  → Delivery (vehicles)...');

        $vehicles = [
            ['code' => 'VEH-TR-001', 'name' => 'Delivery Truck 1',  'type' => 'truck', 'model' => 'Isuzu Forward',    'plate' => 'KBA-1234'],
            ['code' => 'VEH-TR-002', 'name' => 'Delivery Truck 2',  'type' => 'truck', 'model' => 'Mitsubishi Canter','plate' => 'KBA-5678'],
            ['code' => 'VEH-VN-001', 'name' => 'Company Van 1',     'type' => 'van',   'model' => 'Toyota HiAce',     'plate' => 'KBC-9012'],
        ];

        foreach ($vehicles as $v) {
            DB::table('vehicles')->insertOrIgnore([
                'ulid'       => (string) Str::ulid(),
                'code'       => $v['code'],
                'name'       => $v['name'],
                'type'       => $v['type'],
                'make_model' => $v['model'],
                'plate_number'=> $v['plate'],
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FIXED ASSETS
    // ═══════════════════════════════════════════════════════════════════════

    private function seedFixedAssetsData(User $user): void
    {
        $this->command->info('  → Fixed Assets...');

        $categories = [
            ['name' => 'Buildings & Structures', 'code_prefix' => 'BLDG', 'life' => 25, 'method' => 'straight_line'],
            ['name' => 'Machinery & Equipment',  'code_prefix' => 'MACH', 'life' => 10, 'method' => 'straight_line'],
            ['name' => 'Vehicles & Transport',   'code_prefix' => 'VEH',  'life' => 5,  'method' => 'straight_line'],
            ['name' => 'Office Equipment',        'code_prefix' => 'OFCE', 'life' => 5,  'method' => 'straight_line'],
            ['name' => 'IT Equipment',            'code_prefix' => 'IT',   'life' => 3,  'method' => 'straight_line'],
        ];

        $catIds = [];
        foreach ($categories as $cat) {
            $existing = DB::table('fixed_asset_categories')->where('name', $cat['name'])->first();
            if ($existing) {
                $catIds[$cat['code_prefix']] = $existing->id;
                continue;
            }
            $id = DB::table('fixed_asset_categories')->insertGetId([
                'ulid'          => (string) Str::ulid(),
                'name'          => $cat['name'],
                'code_prefix'   => $cat['code_prefix'],
                'created_by_id' => $user->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $catIds[$cat['code_prefix']] = $id;
        }

        $dept = DB::table('departments')->first();

        $assets = [
            [
                'code'      => 'FA-BLDG-001', 'cat' => 'BLDG', 'dept' => $dept?->id,
                'name'      => 'Factory Building — Main Plant',
                'desc'      => '3-storey industrial building, 2,500 sqm GFA',
                'acq_date'  => now()->subYears(8)->toDateString(),
                'cost'      => 5000000000, // ₱50M
                'residual'  => 500000000,  // ₱5M
                'life'      => 25, 'method' => 'straight_line',
                'accum_dep' => 1520000000, // 8/25 of (50M-5M)
                'status'    => 'active', 'location' => 'Plant Complex, Laguna',
            ],
            [
                'code'      => 'FA-MACH-001', 'cat' => 'MACH', 'dept' => $dept?->id,
                'name'      => 'Injection Molding Machine #1 (Toshiba)',
                'desc'      => '500-ton injection molding machine, 2018 model',
                'acq_date'  => now()->subYears(6)->toDateString(),
                'cost'      => 750000000,  // ₱7.5M
                'residual'  => 75000000,   // ₱750K
                'life'      => 10, 'method' => 'straight_line',
                'accum_dep' => 405000000,  // 6/10 of (7.5M-750K)
                'status'    => 'active', 'location' => 'Production Hall A',
            ],
            [
                'code'      => 'FA-MACH-002', 'cat' => 'MACH', 'dept' => $dept?->id,
                'name'      => 'Injection Molding Machine #2 (Mitsubishi)',
                'desc'      => '350-ton injection molding machine, 2020 model',
                'acq_date'  => now()->subYears(4)->toDateString(),
                'cost'      => 600000000,  // ₱6M
                'residual'  => 60000000,   // ₱600K
                'life'      => 10, 'method' => 'straight_line',
                'accum_dep' => 216000000,  // 4/10 of (6M-600K)
                'status'    => 'active', 'location' => 'Production Hall A',
            ],
            [
                'code'      => 'FA-VEH-001', 'cat' => 'VEH', 'dept' => $dept?->id,
                'name'      => 'Delivery Truck — Isuzu Forward',
                'desc'      => '5-ton delivery truck, 2022 model',
                'acq_date'  => now()->subYears(2)->toDateString(),
                'cost'      => 250000000,  // ₱2.5M
                'residual'  => 50000000,   // ₱500K
                'life'      => 5, 'method' => 'straight_line',
                'accum_dep' => 80000000,   // 2/5 of (2.5M-500K)
                'status'    => 'active', 'location' => 'Company Parking',
            ],
            [
                'code'      => 'FA-IT-001', 'cat' => 'IT', 'dept' => $dept?->id,
                'name'      => 'ERP Server Hardware',
                'desc'      => 'Dell PowerEdge R750 server with 32-core CPU, 256GB RAM',
                'acq_date'  => now()->subYears(1)->toDateString(),
                'cost'      => 45000000,   // ₱450K
                'residual'  => 5000000,    // ₱50K
                'life'      => 3, 'method' => 'straight_line',
                'accum_dep' => 13333333,   // 1/3 of (450K-50K)
                'status'    => 'active', 'location' => 'Server Room',
            ],
            [
                'code'      => 'FA-MACH-003', 'cat' => 'MACH', 'dept' => $dept?->id,
                'name'      => 'Air Compressor 75kW (Atlas Copco)',
                'desc'      => 'Screw compressor, 75kW, GA75VSD model',
                'acq_date'  => now()->subYears(3)->toDateString(),
                'cost'      => 120000000,  // ₱1.2M
                'residual'  => 12000000,   // ₱120K
                'life'      => 10, 'method' => 'straight_line',
                'accum_dep' => 32400000,   // 3/10 of (1.2M-120K)
                'status'    => 'impaired', 'location' => 'Utility Room',
            ],
        ];

        foreach ($assets as $a) {
            if (DB::table('fixed_assets')->where('asset_code', $a['code'])->exists()) {
                continue;
            }
            if (! isset($catIds[$a['cat']]) || ! $a['dept']) {
                continue;
            }

            DB::table('fixed_assets')->insert([
                'ulid'                            => (string) Str::ulid(),
                'asset_code'                      => $a['code'],
                'category_id'                     => $catIds[$a['cat']],
                'department_id'                   => $a['dept'],
                'name'                            => $a['name'],
                'description'                     => $a['desc'],
                'acquisition_date'                => $a['acq_date'],
                'acquisition_cost_centavos'       => $a['cost'],
                'residual_value_centavos'         => $a['residual'],
                'useful_life_years'               => $a['life'],
                'depreciation_method'             => $a['method'],
                'accumulated_depreciation_centavos'=> $a['accum_dep'],
                'status'                          => $a['status'],
                'location'                        => $a['location'],
                'created_by_id'                   => $user->id,
                'created_at'                      => now(),
                'updated_at'                      => now(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // BANKING
    // ═══════════════════════════════════════════════════════════════════════

    private function seedBankAccounts(User $user): void
    {
        $this->command->info('  → Bank accounts...');

        $cashAccount = DB::table('chart_of_accounts')
            ->where('name', 'LIKE', '%Cash%')
            ->orWhere('name', 'LIKE', '%Bank%')
            ->value('id');

        $accounts = [
            [
                'name'           => 'BDO Current Account',
                'account_number' => '001-234-567-8',
                'bank_name'      => 'Banco de Oro (BDO)',
                'account_type'   => 'checking',
                'opening_balance'=> 5000000.00,
                'account_id'     => $cashAccount,
            ],
            [
                'name'           => 'Metrobank Savings',
                'account_number' => '028-9876-5432-1',
                'bank_name'      => 'Metropolitan Bank (Metrobank)',
                'account_type'   => 'savings',
                'opening_balance'=> 3500000.00,
                'account_id'     => $cashAccount,
            ],
            [
                'name'           => 'PNB Payroll Account',
                'account_number' => '3456-7890-1234',
                'bank_name'      => 'Philippine National Bank (PNB)',
                'account_type'   => 'checking',
                'opening_balance'=> 1500000.00,
                'account_id'     => $cashAccount,
            ],
        ];

        foreach ($accounts as $acct) {
            if (DB::table('bank_accounts')->where('account_number', $acct['account_number'])->exists()) {
                continue;
            }
            DB::table('bank_accounts')->insert([
                'name'            => $acct['name'],
                'account_number'  => $acct['account_number'],
                'bank_name'       => $acct['bank_name'],
                'account_type'    => $acct['account_type'],
                'account_id'      => $acct['account_id'],
                'opening_balance' => $acct['opening_balance'],
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }
}
