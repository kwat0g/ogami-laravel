<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\AP\Models\Vendor;
use App\Domains\AR\Models\Customer;
use App\Domains\Inventory\Models\ItemMaster;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ManualTestingSeeder
 *
 * Enriches the existing seeded data for comprehensive manual / exploratory testing.
 * Safe to run multiple times (idempotent — uses firstOrCreate / updateOrCreate).
 *
 * Run: php artisan db:seed --class=ManualTestingSeeder
 *
 * After running, check the printed reference card at the end.
 */
class ManualTestingSeeder extends Seeder
{
    // Shared password for ALL test portal accounts
    private const PORTAL_PASSWORD = 'Portal@Test1234!';

    private const STAFF_PASSWORD = 'Staff@Test1234!';

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════');
        $this->command->info('  ManualTestingSeeder — enriching test data…');
        $this->command->info('═══════════════════════════════════════════════════');

        $this->seedVendorPortalUsers();
        $this->seedClientPortalUsers();
        $this->seedFinishedGoodStandardPrices();
        $this->seedFinishedGoodStock();
        $this->seedMoreVendorItems();

        $this->printReferenceCard();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Vendor portal accounts — one per vendor
    // ─────────────────────────────────────────────────────────────────────────

    private function seedVendorPortalUsers(): void
    {
        $vendorPortalAccounts = [
            1 => ['email' => 'vendor.petrochem@ogamierp.local', 'name' => 'Petrochem Vendor Portal'],
            2 => ['email' => 'vendor.packaging@ogamierp.local', 'name' => 'Packaging Solutions Vendor'],
            3 => ['email' => 'vendor.industrial@ogamierp.local', 'name' => 'Industrial Parts Vendor'],
            4 => ['email' => 'vendor.chemlube@ogamierp.local',  'name' => 'ChemLube Vendor Portal'],
        ];

        foreach ($vendorPortalAccounts as $vendorId => $data) {
            $vendor = Vendor::find($vendorId);
            if (! $vendor) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make(self::PORTAL_PASSWORD),
                    'vendor_id' => $vendorId,
                ]
            );

            // Ensure role is assigned
            if (! $user->hasRole('vendor')) {
                $user->assignRole('vendor');
            }

            $this->command->info("  [vendor] {$data['email']} → {$vendor->name}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Client portal accounts — one per customer
    // ─────────────────────────────────────────────────────────────────────────

    private function seedClientPortalUsers(): void
    {
        $clientPortalAccounts = [
            1 => ['email' => 'client.megafoods@ogami.test',    'name' => 'Mega Foods Buyer'],
            2 => ['email' => 'client.packaging@ogami.test',    'name' => 'Southern Packaging Buyer'],
            3 => ['email' => 'client.northluzon@ogami.test',   'name' => 'North Luzon Buyer'],
            4 => ['email' => 'client@ogami.test',              'name' => 'Test Client User'],  // existing, ensure correct
        ];

        foreach ($clientPortalAccounts as $customerId => $data) {
            $customer = Customer::find($customerId);
            if (! $customer) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make(self::PORTAL_PASSWORD),
                    'client_id' => $customerId,
                ]
            );

            // Ensure client_id is linked
            if ($user->client_id !== $customerId) {
                $user->update(['client_id' => $customerId]);
            }

            // Ensure role is assigned
            if (! $user->hasRole('client')) {
                $user->assignRole('client');
            }

            $this->command->info("  [client] {$data['email']} → {$customer->name}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Standard selling prices on finished goods (required for client portal)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedFinishedGoodStandardPrices(): void
    {
        // [item_code => standard_price_centavos]
        $prices = [
            'FG-CONT-1L' => 5_000,    // ₱50.00 / pcs
            'FG-CONT-5L' => 18_000,   // ₱180.00 / pcs
            'FG-CAP-001' => 800,      // ₱8.00  / pcs
            'FG-PLASTIC-001' => 15_000,   // ₱150.00 / pcs
            'FG-METAL-002' => 28_500,   // ₱285.00 / pcs
            'FG-ASSY-003' => 125_000,  // ₱1,250.00 / set
        ];

        foreach ($prices as $code => $priceCentavos) {
            $updated = ItemMaster::where('item_code', $code)
                ->where(fn ($q) => $q
                    ->whereNull('standard_price_centavos')
                    ->orWhere('standard_price_centavos', 0)
                )
                ->update(['standard_price_centavos' => $priceCentavos]);

            if ($updated) {
                $price = number_format($priceCentavos / 100, 2);
                $this->command->info("  [price] {$code} → ₱{$price}");
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Ensure finished goods have good stock in the FG warehouse
    // ─────────────────────────────────────────────────────────────────────────

    private function seedFinishedGoodStock(): void
    {
        // WH-FG-01 = Finished Goods Storage (location_id=4)
        $fgLocationId = DB::table('warehouse_locations')
            ->where('code', 'WH-FG-01')
            ->value('id');

        if (! $fgLocationId) {
            $this->command->warn('  WH-FG-01 location not found — skipping FG stock');

            return;
        }

        // [item_code => quantity to ensure on hand]
        $fgStock = [
            'FG-CONT-1L' => 5_000,
            'FG-CONT-5L' => 3_000,
            'FG-CAP-001' => 10_000,
            'FG-PLASTIC-001' => 2_000,
            'FG-METAL-002' => 1_500,
            'FG-ASSY-003' => 500,
        ];

        foreach ($fgStock as $code => $qty) {
            $item = ItemMaster::where('item_code', $code)->first();
            if (! $item) {
                continue;
            }

            $existing = DB::table('stock_balances')
                ->where('item_id', $item->id)
                ->where('location_id', $fgLocationId)
                ->first();

            if ($existing) {
                // Only update if current stock is very low (don't overwrite real transactions)
                if ((float) $existing->quantity_on_hand < ($qty * 0.1)) {
                    DB::table('stock_balances')
                        ->where('item_id', $item->id)
                        ->where('location_id', $fgLocationId)
                        ->update(['quantity_on_hand' => $qty, 'updated_at' => now()]);
                    $this->command->info("  [stock] {$code} → qty {$qty} (topped up)");
                }
            } else {
                DB::table('stock_balances')->insert([
                    'item_id' => $item->id,
                    'location_id' => $fgLocationId,
                    'quantity_on_hand' => $qty,
                    'quantity_reserved' => 0,
                    'updated_at' => now(),
                ]);
                $this->command->info("  [stock] {$code} → qty {$qty} (created)");
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Ensure vendor items have realistic priced entries
    // ─────────────────────────────────────────────────────────────────────────

    private function seedMoreVendorItems(): void
    {
        $adminId = User::where('email', 'superadmin@ogamierp.local')->value('id') ?? 1;

        // Vendor 2 (Packaging Solutions) — packaging items
        $this->upsertVendorItem(2, 'PKG-BOXX-L', 'Carton Box Large', 'pcs', 350, $adminId);
        $this->upsertVendorItem(2, 'PKG-WRAP-001', 'Stretch Wrap Film', 'roll', 1_200, $adminId);

        // Vendor 3 (Industrial Parts) — spare parts and MRO
        $this->upsertVendorItem(3, 'SP-SEAL-01', 'Hydraulic Seal Kit', 'set', 2_500, $adminId);
        $this->upsertVendorItem(3, 'SP-BEARING-01', 'Roller Bearing 6205', 'pcs', 1_850, $adminId);
        $this->upsertVendorItem(3, 'SP-FILTER-01', 'Air Filter Element', 'pcs', 750, $adminId);

        // Vendor 4 (ChemLube) — lubricants and chemicals
        $this->upsertVendorItem(4, 'OIL-HYD-68', 'Hydraulic Oil 68', 'L', 9_500, $adminId);
        $this->upsertVendorItem(4, 'LUB-GREASE-1', 'Multi-Purpose Grease', 'kg', 3_200, $adminId);
    }

    private function upsertVendorItem(
        int $vendorId,
        string $code,
        string $name,
        string $uom,
        int $price,
        int $createdById
    ): void {
        // Guard: skip if vendor doesn't exist (avoids FK violation in test DBs)
        if (! Vendor::find($vendorId)) {
            return;
        }

        $existing = DB::table('vendor_items')
            ->where('vendor_id', $vendorId)
            ->where('item_code', $code)
            ->exists();

        if ($existing) {
            return; // Already there
        }

        DB::table('vendor_items')->insert([
            'ulid' => Str::ulid(),
            'vendor_id' => $vendorId,
            'item_code' => $code,
            'item_name' => $name,
            'unit_of_measure' => $uom,
            'unit_price' => $price,
            'is_active' => true,
            'created_by_id' => $createdById,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reference card — printed at the end of seeding
    // ─────────────────────────────────────────────────────────────────────────

    private function printReferenceCard(): void
    {
        $pw = self::PORTAL_PASSWORD;
        $spw = self::STAFF_PASSWORD;

        $lines = [
            '',
            '╔══════════════════════════════════════════════════════════════════════╗',
            '║               OGAMI ERP — MANUAL TESTING REFERENCE CARD            ║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  All portal passwords : '.str_pad($pw, 47).'║',
            '╠═══════════════════════════╦══════════════════════════════════════════╣',
            '║  ROLE / PERSONA           ║  EMAIL                                  ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  Super Admin              ║  superadmin@ogamierp.local              ║',
            '║  Vice President           ║  vp@ogamierp.local                      ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  ── ACCOUNTING ──         ║                                         ║',
            '║  Acctg Head               ║  acctg.head@ogamierp.local              ║',
            '║  Acctg Manager            ║  acctg.manager@ogamierp.local           ║',
            '║  Acctg Officer            ║  accounting@ogamierp.local              ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  ── SALES ──              ║                                         ║',
            '║  Sales Head               ║  sales.head@ogamierp.local              ║',
            '║  Sales Manager            ║  sales.manager@ogamierp.local           ║',
            '║  Sales Officer            ║  sales.officer@ogamierp.local           ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  ── PROCUREMENT ──        ║                                         ║',
            '║  Purchasing Officer       ║  purchasing.officer@ogamierp.local      ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  ── WAREHOUSE ──          ║                                         ║',
            '║  Warehouse Head           ║  warehouse.head@ogamierp.local          ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  ── HR ──                 ║                                         ║',
            '║  HR Head                  ║  hr.head@ogamierp.local                 ║',
            '║  HR Officer               ║  hr.officer@ogamierp.local              ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  ── PRODUCTION ──         ║                                         ║',
            '║  Production Head          ║  prod.head@ogamierp.local               ║',
            '║  Production Manager       ║  prod.manager@ogamierp.local            ║',
            '║  Production Officer       ║  prod.officer@ogamierp.local            ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  ── QC ──                 ║                                         ║',
            '║  QC Head                  ║  qc.head@ogamierp.local                 ║',
            '║  QC Officer               ║  qc.officer@ogamierp.local              ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  ── MAINTENANCE ──        ║                                         ║',
            '║  Maintenance Head         ║  maintenance.head@ogamierp.local        ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  ── CLIENT PORTAL ──      ║  (place orders, track status)           ║',
            '║  ABC Manufacturing Corp   ║  client@ogami.test                      ║',
            '║  Mega Foods Corporation   ║  client.megafoods@ogami.test            ║',
            '║  Southern Packaging       ║  client.packaging@ogami.test            ║',
            '║  North Luzon Distributors ║  client.northluzon@ogami.test           ║',
            '╠═══════════════════════════╬══════════════════════════════════════════╣',
            '║  ── VENDOR PORTAL ──      ║  (view POs, deliver, invoice)           ║',
            '║  Petrochem Supplies       ║  vendor.petrochem@ogamierp.local        ║',
            '║  Packaging Solutions      ║  vendor.packaging@ogamierp.local        ║',
            '║  Industrial Parts Trading ║  vendor.industrial@ogamierp.local       ║',
            '║  ChemLube Philippines     ║  vendor.chemlube@ogamierp.local         ║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  All staff/portal passwords: '.str_pad($pw, 42).'║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  KEY REFERENCE DATA                                                 ║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  CUSTOMERS                                                          ║',
            '║  [1] Mega Foods Corp           — credit limit ₱5,000,000           ║',
            '║  [2] Southern Packaging Traders — credit limit ₱2,000,000          ║',
            '║  [3] North Luzon Distributors  — credit limit ₱1,500,000           ║',
            '║  [4] ABC Manufacturing Corp    — credit limit ₱10,000,000          ║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  VENDORS (all accredited)                                           ║',
            '║  [1] Petrochem Supplies Inc.   — PE/PP resin, masterbatch          ║',
            '║  [2] Packaging Solutions Corp. — carton boxes, stretch wrap        ║',
            '║  [3] Industrial Parts Trading  — screws, belts, bearings, filters  ║',
            '║  [4] ChemLube Philippines      — hydraulic oils, lubricants        ║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  FINISHED GOODS (for client portal ordering)                        ║',
            '║  [5]  FG-CONT-1L    Plastic Container 1L  — ₱50/pcs               ║',
            '║  [6]  FG-CONT-5L    Plastic Container 5L  — ₱180/pcs              ║',
            '║  [7]  FG-CAP-001    Plastic Cap Standard  — ₱8/pcs                ║',
            '║  [12] FG-PLASTIC-001 Plastic Component A  — ₱150/pcs              ║',
            '║  [13] FG-METAL-002  Metal Bracket B       — ₱285/pcs              ║',
            '║  [14] FG-ASSY-003   Assembly Unit C       — ₱1,250/set            ║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  RAW MATERIALS (for PR/PO workflow testing)                         ║',
            '║  [1]  RM-PE-001   Polyethylene Resin      — vendor: Petrochem      ║',
            '║  [2]  RM-PP-001   Polypropylene Resin     — vendor: Petrochem      ║',
            '║  [3]  RM-MB-001   Color Masterbatch Black — vendor: Petrochem      ║',
            '║  [4]  RM-MB-002   Color Masterbatch White — vendor: Petrochem      ║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  WAREHOUSE LOCATIONS                                                ║',
            '║  [1] WH-MAIN-01  Main Warehouse Zone A   (general storage)         ║',
            '║  [2] WH-MAIN-02  Main Warehouse Zone B   (overflow)                ║',
            '║  [3] WH-RM-01    Raw Materials Storage   (raw mat receipts)        ║',
            '║  [4] WH-FG-01    Finished Goods Storage  (FG dispatch)             ║',
            '║  [5] WH-QA-01    QA Hold Area            (inspection hold)         ║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  FISCAL PERIODS                                                     ║',
            '║  Feb 2026 & Mar 2026 are OPEN — use these for all transactions      ║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  VP APPROVAL THRESHOLDS                                             ║',
            '║  Client Orders   ≥ ₱500,000  → requires VP approval               ║',
            '║  Purchase Orders: VP submits own PR → auto-advances to reviewed    ║',
            '╠══════════════════════════════════════════════════════════════════════╣',
            '║  COMMON WORKFLOW PATHS                                              ║',
            '║                                                                     ║',
            '║  Client Order:  client → sales.head (approve/negotiate) → vp       ║',
            '║  PR Workflow:   any head → purchasing.officer (review) →           ║',
            '║                 accounting (budget) → vp (final approve)           ║',
            '║  PO Workflow:   purchasing.officer → vendor portal → warehouse.head ║',
            '║  Vendor Invoice: vendor portal → acctg.head → vp (pay)            ║',
            '╚══════════════════════════════════════════════════════════════════════╝',
            '',
        ];

        foreach ($lines as $line) {
            $this->command->line($line);
        }
    }
}
