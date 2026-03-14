<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * CORE_MODULES_TESTING_GUIDE — Sequential E2E walkthrough
 *
 * Run: ./vendor/bin/pest tests/E2E/CoreModulesGuideTest.php --no-coverage -v
 *
 * Uses DatabaseMigrations (not RefreshDatabase) so state persists across the
 * single sequential test. All 11 scenarios run in one transaction-free test.
 */

uses(Illuminate\Foundation\Testing\DatabaseMigrations::class);

$state = new stdClass();

function a(): User
{
    return User::where('email', 'superadmin@ogamierp.local')->firstOrFail();
}

function go(string $method, string $path, array $data = []): \Illuminate\Testing\TestResponse
{
    $resp = test()->actingAs(a())->json(strtoupper($method), '/api/v1/' . ltrim($path, '/'), $data);
    if ($resp->status() >= 400) {
        echo "\n  ⚡ [{$resp->status()}] {$method} /api/v1/{$path} → " . substr($resp->getContent(), 0, 500) . "\n";
    }
    return $resp;
}

test('Full CORE_MODULES_TESTING_GUIDE walkthrough', function () use ($state) {
    $this->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class]);

    Artisan::call('db:seed');
    $superadmin = a();
    expect($superadmin)->not->toBeNull('Superadmin user missing after seed');
    echo "\n✅ DB seeded. Superadmin: {$superadmin->email}\n";

    // ═══ SCENARIO 0 — MASTER DATA SETUP ═══
    echo "\n▶▶ SCENARIO 0 — Master Data Setup\n";

    $coaId = DB::table('chart_of_accounts')->where('code', '1001')->value('id') ?? 1;
    $r = go('POST', 'accounting/bank-accounts', [
        'name'            => 'BDO Main',
        'account_number'  => '0000-1234-5678',
        'bank_name'       => 'Banco de Oro',
        'account_type'    => 'checking',
        'account_id'      => $coaId,
        'opening_balance' => 500000,
        'is_active'       => true,
    ]);
    $r->assertStatus(201, "0.1 Create bank account — got {$r->status()}: " . $r->getContent());
    $state->bankAccountId = $r->json('data.id');
    echo "  ✅ 0.1 Bank account id={$state->bankAccountId}\n";

    // [FIXED: ap/vendors → accounting/vendors]
    $r = go('POST', 'accounting/vendors', [
        'name'           => 'Chinatown Resins Inc.',
        'tin'            => '000-123-456-000',
        'contact_person' => 'Juan Dela Cruz',
        'email'          => 'sales@chinatownresins.test',
        'phone'          => '+63 2 8888 0001',
        'address'        => '12 Resin Street, Tondo, Manila',
        'is_ewt_subject' => false,
        'payment_terms'  => 'NET30',
    ]);
    $r->assertStatus(201, "0.2 Create vendor — got {$r->status()}: " . $r->getContent());
    $state->vendorId = $r->json('data.id');
    echo "  ✅ 0.2 Vendor id={$state->vendorId}\n";

    // 0.2b Create vendor item
    $r = go('POST', "accounting/vendors/{$state->vendorId}/items", [
        'item_code'       => 'V-PP-NAT',
        'item_name'       => 'PP Resin Natural (Vendor Item)',
        'unit_of_measure' => 'kg',
        'unit_price'      => 18000, // 180.00
    ]);
    $r->assertStatus(201, "0.2b Create vendor item — got {$r->status()}: " . $r->getContent());
    $state->vendorItemId = $r->json('data.id');
    echo "  ✅ 0.2b Vendor Item id={$state->vendorItemId}\n";

    $r = go('POST', 'ar/customers', [
        'name'           => 'Ace Hardware Philippines',
        'tin'            => '000-987-654-000',
        'credit_limit'   => 500000,
        'email'          => 'procurement@acehw.test',
        'phone'          => '+63 2 8999 0002',
        'contact_person' => 'Maria Cruz',
        'address'        => '1 Hardware Ave, Pasig, Metro Manila',
    ]);
    $r->assertStatus(201, "0.3 Create customer — got {$r->status()}: " . $r->getContent());
    $state->customerId = $r->json('data.id');
    echo "  ✅ 0.3 Customer id={$state->customerId}\n";

    $r = go('POST', 'inventory/items/categories', [
        'code'        => 'RAW-MAT',
        'name'        => 'Raw Materials',
        'description' => 'Plastic pellets and raw resin inputs',
    ]);
    $r->assertStatus(201, "0.4 Create item category — got {$r->status()}: " . $r->getContent());
    $state->catId = $r->json('data.id');
    echo "  ✅ 0.4 Item category id={$state->catId}\n";

    $r = go('POST', 'inventory/items', [
        'category_id'     => $state->catId,
        'name'            => 'PP Resin Natural',
        'type'            => 'raw_material',
        'unit_of_measure' => 'kg',
        'reorder_point'   => 500,
        'reorder_qty'     => 2000,
        'requires_iqc'    => true,
    ]);
    $r->assertStatus(201, "0.5a Create PP Resin Natural — got {$r->status()}: " . $r->getContent());
    $state->raw1Id = $r->json('data.id');
    echo "  ✅ 0.5a PP Resin Natural id={$state->raw1Id}\n";

    $r = go('POST', 'inventory/items', [
        'category_id'     => $state->catId,
        'name'            => 'HDPE Resin Black',
        'type'            => 'raw_material',
        'unit_of_measure' => 'kg',
        'reorder_point'   => 300,
        'reorder_qty'     => 1000,
        'requires_iqc'    => true,
    ]);
    $r->assertStatus(201, "0.5b Create HDPE Resin Black — got {$r->status()}: " . $r->getContent());
    $state->raw2Id = $r->json('data.id');
    echo "  ✅ 0.5b HDPE Resin Black id={$state->raw2Id}\n";

    $r = go('POST', 'inventory/items', [
        'category_id'     => $state->catId,
        'name'            => 'Plastic Container 500ml',
        'type'            => 'finished_good',
        'unit_of_measure' => 'pcs',
        'reorder_point'   => 1000,
        'reorder_qty'     => 5000,
        'requires_iqc'    => true,
    ]);
    $r->assertStatus(201, "0.5c Create Plastic Container 500ml — got {$r->status()}: " . $r->getContent());
    $state->fgId = $r->json('data.id');
    echo "  ✅ 0.5c Plastic Container 500ml id={$state->fgId}\n";

    $r = go('POST', 'inventory/locations', [
        'code' => 'WH-A1',
        'name' => 'Warehouse A – Rack 1',
        'zone' => 'A',
        'bin'  => 'Rack-01',
    ]);
    $r->assertStatus(201, "0.6 Create warehouse location — got {$r->status()}: " . $r->getContent());
    $state->locationId = $r->json('data.id');
    echo "  ✅ 0.6 Warehouse location created\n";

    $r = go('POST', 'production/boms', [
        'product_item_id' => $state->fgId,
        'version'         => '1.0',
        'notes'           => 'Injection-moulded 500ml PP container.',
        'components'      => [
            ['component_item_id' => $state->raw1Id, 'qty_per_unit' => 0.0192, 'unit_of_measure' => 'kg', 'scrap_factor_pct' => 4],
            ['component_item_id' => $state->raw2Id, 'qty_per_unit' => 0.0008, 'unit_of_measure' => 'kg', 'scrap_factor_pct' => 2],
        ],
    ]);
    $r->assertStatus(201, "0.7 Create BOM — got {$r->status()}: " . $r->getContent());
    $state->bomId   = $r->json('data.id');
    $state->bomUlid = $r->json('data.ulid');
    echo "  ✅ 0.7 BOM id={$state->bomId}\n";

    go('PATCH', "production/boms/{$state->bomUlid}/activate")
        ->assertStatus(200, '0.7b BOM activate');
    echo "  ✅ 0.7b BOM activated\n";

    // ═══ SCENARIO 1 — PROCUREMENT ═══
    echo "\n▶▶ SCENARIO 1 — Procurement\n";

    $deptId = DB::table('departments')->where('code', 'PROD')->value('id')
              ?? DB::table('departments')->first()?->id;

    $r = go('POST', 'procurement/purchase-requests', [
        'department_id' => $deptId,
        'vendor_id'     => $state->vendorId,
        'urgency'       => 'normal',
        'justification' => 'PP Resin Natural stock is below the safety level threshold of 500kg.',
        'items'         => [
            [
                'vendor_item_id'      => $state->vendorItemId,
                'item_description'    => 'PP Resin Natural',
                'unit_of_measure'     => 'kg',
                'quantity'            => 500,
                'estimated_unit_cost' => 180
            ],
        ],
    ]);
    $r->assertStatus(201, "1.1 Create PR — got {$r->status()}: " . $r->getContent());
    $prUlid = $r->json('data.ulid');
    $prId   = $r->json('data.id');
    echo "  ✅ 1.1 PR created ulid={$prUlid}\n";

    $ra = go('POST', "procurement/purchase-requests/{$prUlid}/submit");
    $ra->assertStatus(200, "1.1b Submit PR — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 1.1b PR submitted\n";

    // Superadmin submission auto-notes (Hierarchy Bypass), so we skip 'note'
    foreach (['check', 'review', 'budget-check', 'vp-approve'] as $action) {
        $ra = go('POST', "procurement/purchase-requests/{$prUlid}/{$action}", ['remarks' => 'Approved']);
        $ra->assertStatus(200, "1.2 PR {$action} — got {$ra->status()}: " . $ra->getContent());
        echo "  ✅ 1.2 PR {$action}\n";
    }

    // [FIXED: no create-po nested route; PO is standalone resource]
    // PO is auto-created after PR approval. We fetch it and update it.
    $po = DB::table('purchase_orders')->where('purchase_request_id', $prId)->first();
    expect($po)->not->toBeNull('PO should be auto-created after PR approval');
    $poId = $po->id;
    $poUlid = $po->ulid;
    echo "  ✅ 1.3 PO auto-created ulid={$poUlid}\n";

    // Fetch PR item ID for linking
    $prItemId = DB::table('purchase_request_items')->where('purchase_request_id', $prId)->value('id');

    // Update PO details AND map item to ItemMaster
    $r = go('PATCH', "procurement/purchase-orders/{$poUlid}", [
        'delivery_date' => now()->addDays(30)->toDateString(),
        'payment_terms' => 'NET30',
        'items' => [
            [
                'pr_item_id'       => $prItemId,
                'item_master_id'   => $state->raw1Id, // Map to PP Resin Natural
                'item_description' => 'PP Resin Natural',
                'unit_of_measure'  => 'kg',
                'quantity_ordered' => 500,
                'agreed_unit_cost' => 180,
            ],
        ],
    ]);
    $r->assertStatus(200, "1.3a Update PO — got {$r->status()}: " . $r->getContent());

    $ra = go('POST', "procurement/purchase-orders/{$poUlid}/send");
    $ra->assertStatus(200, "1.3b Send PO — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 1.3b PO sent\n";

    $poItemId = DB::table('purchase_order_items')->where('purchase_order_id', $poId)->whereNull('deleted_at')->value('id');

    // [FIXED: standalone POST procurement/goods-receipts; quantity_received not qty_received]
    $r = go('POST', 'procurement/goods-receipts', [
        'purchase_order_id' => $poId,
        'received_date'     => now()->subDay()->toDateString(),
        'items'             => [
            ['po_item_id' => $poItemId, 'quantity_received' => 498, 'unit_of_measure' => 'kg'],
        ],
    ]);
    $r->assertStatus(201, "1.4 Create GR1 — got {$r->status()}: " . $r->getContent());
    $gr1Ulid = $r->json('data.ulid');
    echo "  ✅ 1.4 GR1 ulid={$gr1Ulid}\n";

    $ra = go('POST', "procurement/goods-receipts/{$gr1Ulid}/confirm");
    $ra->assertStatus(200, "1.4b Confirm GR1 — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 1.4b GR1 confirmed\n";

    sleep(2);
    $stock = (float) DB::table('stock_balances')->where('item_id', $state->raw1Id)->sum('quantity_on_hand');
    expect($stock)->toBeGreaterThanOrEqual(498, "1.4c Stock should be ≥498 after GR1, got {$stock}");
    echo "  ✅ 1.4c Stock = {$stock} kg after GR1\n";

    $r = go('POST', 'procurement/goods-receipts', [
        'purchase_order_id' => $poId,
        'received_date'     => now()->toDateString(),
        'items'             => [
            ['po_item_id' => $poItemId, 'quantity_received' => 2, 'unit_of_measure' => 'kg'],
        ],
    ]);
    $r->assertStatus(201, "1.5 Create GR2 — got {$r->status()}: " . $r->getContent());
    $gr2Ulid = $r->json('data.ulid');

    $ra = go('POST', "procurement/goods-receipts/{$gr2Ulid}/confirm");
    $ra->assertStatus(200, "1.5b Confirm GR2 — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 1.5 GR2 confirmed\n";

    sleep(2);
    $stock = (float) DB::table('stock_balances')->where('item_id', $state->raw1Id)->sum('quantity_on_hand');
    expect(abs($stock - 500))->toBeLessThan(1, "1.5c Total stock should be ~500 kg, got {$stock}");
    echo "  ✅ 1.5c Total stock = {$stock} kg\n";

    $invCount = DB::table('vendor_invoices')->where('vendor_id', $state->vendorId)->count();
    expect($invCount)->toBe(2, "1.5d Expected 2 AP invoices auto-created, got {$invCount}");
    echo "  ✅ 1.5d AP invoices = {$invCount}\n";

    // ═══ SCENARIO 2 — INVENTORY IQC & MRQ ═══
    echo "\n▶▶ SCENARIO 2 — Inventory IQC & MRQ\n";

    $r = go('POST', 'qc/inspections', [
        'stage'           => 'iqc',
        'inspection_date' => now()->subDay()->toDateString(),
        'item_id'         => $state->raw1Id,
        'qty_inspected'   => 50,
    ]);
    $r->assertStatus(201, "2.1 Create IQC — got {$r->status()}: " . $r->getContent());
    $iqcUlid = $r->json('data.ulid');

    // [FIXED: submit-results → results; PATCH; is_conforming boolean; qty_passed/qty_failed required]
    $ra = go('PATCH', "qc/inspections/{$iqcUlid}/results", [
        'qty_passed' => 50,
        'qty_failed' => 0,
        'results' => [
            ['criterion' => 'Visual contamination', 'is_conforming' => true, 'actual_value' => null],
            ['criterion' => 'Moisture content',     'is_conforming' => true, 'actual_value' => '0.03%'],
            ['criterion' => 'Melt Flow Index',      'is_conforming' => true, 'actual_value' => '13.2'],
        ],
    ]);
    $ra->assertStatus(200, "2.1b IQC results — got {$ra->status()}: " . $ra->getContent());
    expect($ra->json('data.status'))->toBe('passed', '2.1c IQC status should be "passed"');
    echo "  ✅ 2.1 IQC passed\n";

    $r = go('POST', 'inventory/requisitions', [
        'department_id' => $deptId,
        'purpose'       => 'Materials for March production run',
        'items'         => [['item_id' => $state->raw1Id, 'qty_requested' => 200]],
    ]);
    $r->assertStatus(201, "2.4 Create MRQ — got {$r->status()}: " . $r->getContent());
    $mrqUlid = $r->json('data.ulid');
    echo "  ✅ 2.4 MRQ created\n";

    // [FIXED: POST → PATCH; add submit before approval chain]
    $ra = go('PATCH', "inventory/requisitions/{$mrqUlid}/submit");
    $ra->assertStatus(200, "2.5a MRQ submit — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 2.5a MRQ submitted\n";

    foreach (['note', 'check', 'review', 'vp-approve'] as $action) {
        $ra = go('PATCH', "inventory/requisitions/{$mrqUlid}/{$action}", ['remarks' => 'Approved']);
        $ra->assertStatus(200, "2.5 MRQ {$action} — got {$ra->status()}: " . $ra->getContent());
        echo "  ✅ 2.5 MRQ {$action}\n";
    }

    // [FIXED: POST → PATCH; requires location_id]
    $rf = go('PATCH', "inventory/requisitions/{$mrqUlid}/fulfill", ['location_id' => $state->locationId]);
    $rf->assertStatus(200, "2.6 Fulfill MRQ — got {$rf->status()}: " . $rf->getContent());
    echo "  ✅ 2.6 MRQ fulfilled\n";

    sleep(1);
    $stock = (float) DB::table('stock_balances')->where('item_id', $state->raw1Id)->sum('quantity_on_hand');
    expect(abs($stock - 300))->toBeLessThan(2, "2.6b Stock after MRQ should be ~300 kg, got {$stock}");
    echo "  ✅ 2.6b Post-MRQ stock = {$stock} kg\n";

    // Guide §2.3: stock ledger should show inbound GR transactions for PP Resin
    go('GET', "inventory/stock-ledger?item_id={$state->raw1Id}")
        ->assertStatus(200, '2.3 Stock ledger GET');
    $ledgerCount = DB::table('stock_ledger')->where('item_id', $state->raw1Id)->where('transaction_type', 'goods_receipt')->count();
    expect($ledgerCount)->toBeGreaterThanOrEqual(2, "2.3b Stock ledger should have ≥2 goods_receipt entries for PP Resin, got {$ledgerCount}");
    echo "  ✅ 2.3 Stock ledger ≥{$ledgerCount} inbound entries\n";

    // ═══ SCENARIO 3 — AP INVOICE APPROVAL & PAYMENT ═══
    echo "\n▶▶ SCENARIO 3 — AP Invoice Approval & Payment\n";

    $invoices = DB::table('vendor_invoices')->where('vendor_id', $state->vendorId)->get();
    expect($invoices->count())->toBe(2, '3.1 Should have 2 AP invoices');

    foreach ($invoices as $inv) {
        $label = '₱' . number_format($inv->net_amount / 100, 0);

        // [FIXED: ap/ → accounting/ap/; POST → PATCH; submit-for-approval → submit]
        foreach (['submit', 'head-note', 'manager-check', 'officer-review', 'approve'] as $action) {
            $ra = go('PATCH', "accounting/ap/invoices/{$inv->ulid}/{$action}", ['remarks' => 'OK']);
            $ra->assertStatus(200, "3.2 AP {$action} {$label} — got {$ra->status()}: " . $ra->getContent());
            echo "  ✅ 3.2 {$action} ({$label})\n";
        }

        $net = (int) $inv->net_amount;
        $ewt = (int) round($net * 0.02);
        // [FIXED: ap/ → accounting/ap/; record-payment → payments; no bank_account_id]
        $rb = go('POST', "accounting/ap/invoices/{$inv->ulid}/payments", [
            'amount'         => ($net - $ewt) / 100,
            'payment_date'   => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);
        $rb->assertStatus(201, "3.3 Record payment {$label} — got {$rb->status()}: " . $rb->getContent());
        echo "  ✅ 3.3 Payment recorded ({$label})\n";
    }

    // ═══ SCENARIO 4 — PRODUCTION ORDER ═══
    echo "\n▶▶ SCENARIO 4 — Production Order\n";

    // [FIXED: quantity_ordered → qty_ordered]
    $r = go('POST', 'production/delivery-schedules', [
        'customer_id'          => $state->customerId,
        'product_item_id'      => $state->fgId,
        'qty_ordered'          => 10000,
        'target_delivery_date' => now()->addDays(60)->toDateString(),
        'type'                 => 'local',
    ]);
    $r->assertStatus(201, "4.1 Create delivery schedule — got {$r->status()}: " . $r->getContent());
    $state->dsId = $r->json('data.id');
    echo "  ✅ 4.1 Delivery schedule id={$state->dsId}\n";

    // [FIXED: quantity_required → qty_required]
    $r = go('POST', 'production/orders', [
        'product_item_id'      => $state->fgId,
        'bom_id'               => $state->bomId,
        'delivery_schedule_id' => $state->dsId,
        'qty_required'         => 10000,
        'target_start_date'    => now()->toDateString(),
        'target_end_date'      => now()->addDays(7)->toDateString(),
    ]);
    $r->assertStatus(201, "4.3 Create production order — got {$r->status()}: " . $r->getContent());
    $woUlid = $r->json('data.ulid');
    echo "  ✅ 4.3 Production order ulid={$woUlid}\n";

    // [FIXED: POST → PATCH for release/start/complete]
    $ra = go('PATCH', "production/orders/{$woUlid}/release");
    $ra->assertStatus(200, "4.3b Release WO — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 4.3b WO released\n";

    $ra = go('PATCH', "production/orders/{$woUlid}/start");
    $ra->assertStatus(200, "4.3c Start WO — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 4.3c WO started\n";

    // [FIXED: log-output → output; required fields: shift (A/B/C), log_date (≤today), operator_id]
    $operatorId = DB::table('employees')->first()?->id ?? 1;
    $ra = go('POST', "production/orders/{$woUlid}/output", [
        'shift'        => 'A',
        'log_date'     => now()->toDateString(),
        'qty_produced' => 10050,
        'qty_rejected' => 43,
        'operator_id'  => $operatorId,
    ]);
    $ra->assertStatus(201, "4.4 Log output — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 4.4 Output logged\n";

    $ra = go('PATCH', "production/orders/{$woUlid}/complete");
    $ra->assertStatus(200, "4.4b Complete WO — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 4.4b WO completed\n";

    sleep(2);
    $fgStock = (float) DB::table('stock_balances')->where('item_id', $state->fgId)->sum('quantity_on_hand');
    expect($fgStock)->toBeGreaterThan(10000, "4.4c FG stock should be >10000, got {$fgStock}");
    echo "  ✅ 4.4c FG stock = {$fgStock} pcs\n";

    // ═══ SCENARIO 5 — MAINTENANCE ═══
    echo "\n▶▶ SCENARIO 5 — Maintenance\n";

    $r = go('POST', 'maintenance/equipment', [
        'name'              => 'Injection Moulding Machine #1',
        'category'          => 'Production',
        'manufacturer'      => 'Engel',
        'model_number'      => 'ES200/50',
        'serial_number'     => 'EM-2018-00123',
        'location'          => 'Production Floor A',
        'commissioned_date' => '2018-06-01',
        'status'            => 'operational',
    ]);
    $r->assertStatus(201, "5.0a Create IMM#1 — got {$r->status()}: " . $r->getContent());
    $state->eqId   = $r->json('data.id');
    $state->eqUlid = $r->json('data.ulid');
    echo "  ✅ 5.0a IMM#1 id={$state->eqId}\n";

    go('POST', 'maintenance/equipment', [
        'name'              => 'Hydraulic Press #3',
        'category'          => 'Production',
        'manufacturer'      => 'Schuler',
        'model_number'      => 'HP-320',
        'serial_number'     => 'HP-2015-00789',
        'location'          => 'Production Floor B',
        'commissioned_date' => '2015-03-20',
        'status'            => 'operational',
    ])->assertStatus(201, "5.0b Create HP#3");
    echo "  ✅ 5.0b HP#3 created\n";

    $r = go('POST', 'maintenance/work-orders', [
        'equipment_id'   => $state->eqId,
        'type'           => 'corrective',
        'priority'       => 'critical',
        'title'          => 'Hydraulic system leak — production stoppage',
        'description'    => 'Machine stopped. Hydraulic fluid leaking from main cylinder seal.',
        'scheduled_date' => now()->toDateString(),
    ]);
    $r->assertStatus(201, "5.1 Create corrective WO — got {$r->status()}: " . $r->getContent());
    $maintUlid = $r->json('data.ulid');
    echo "  ✅ 5.1 Corrective WO ulid={$maintUlid}\n";

    // [FIXED: POST → PATCH]
    go('PATCH', "maintenance/work-orders/{$maintUlid}/start")
        ->assertStatus(200, "5.1b Start WO");
    echo "  ✅ 5.1b WO started\n";

    // [FIXED: POST → PATCH]
    go('PATCH', "maintenance/work-orders/{$maintUlid}/complete", [
        'completion_notes'       => 'Replaced main cylinder seal. Machine operational.',
        'actual_completion_date' => now()->toDateString(),
        'labor_hours'            => 3.5,
    ])->assertStatus(200, "5.2 Complete WO");
    echo "  ✅ 5.2 WO completed\n";

    // [FIXED: standalone maintenance/pm-schedules → nested maintenance/equipment/{id}/pm-schedules]
    go('POST', "maintenance/equipment/{$state->eqUlid}/pm-schedules", [
        'task_name'      => 'Monthly Lubrication Service',
        'frequency_days' => 30,
        'last_done_on'   => now()->toDateString(),
    ])->assertStatus(201, "5.3 Create PM schedule");
    echo "  ✅ 5.3 PM schedule created\n";

    // ═══ SCENARIO 6 — MOLD ═══
    echo "\n▶▶ SCENARIO 6 — Mold\n";

    $r = go('POST', 'mold/molds', [
        'name'         => 'Container 500ml – Cavity 4',
        'description'  => '4-cavity mould for 500ml PP container',
        'cavity_count' => 4,
        'material'     => 'P20 Tool Steel',
        'location'     => 'Mold Room Rack A',
        'max_shots'    => 10000,
        'status'       => 'active',
    ]);
    $r->assertStatus(201, "6.0 Create mold — got {$r->status()}: " . $r->getContent());
    $moldUlid = $r->json('data.ulid');
    echo "  ✅ 6.0 Mold ulid={$moldUlid}\n";

    // [FIXED: log-shots → shots; log_date must be ≤ today]
    $ra = go('POST', "mold/molds/{$moldUlid}/shots", [
        'shot_count' => 10050,
        'log_date'   => now()->toDateString(),
        'remarks'    => 'March production run',
    ]);
    $ra->assertStatus(201, "6.2 Log shots — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 6.2 Mold shots logged (10050)\n";

    sleep(1);
    $mold = DB::table('mold_masters')->where('ulid', $moldUlid)->first();
    expect((int) $mold->current_shots)->toBe(10050, '6.2b current_shots should be 10050');
    echo "  ✅ 6.2b current_shots = {$mold->current_shots}\n";

    // Guide §6.2: mold auto-WO is created synchronously in MoldService::logShots when current_shots >= max_shots
    $moldDbId   = DB::table('mold_masters')->where('ulid', $moldUlid)->value('id');
    $moldAutoWo = DB::table('maintenance_work_orders')
        ->where('mold_master_id', $moldDbId)
        ->where('type', 'preventive')
        ->first();
    expect($moldAutoWo)->not->toBeNull('6.2c Mold auto-preventive WO should be created when shots >= max_shots');
    echo "  ✅ 6.2c Mold auto-preventive WO created (id={$moldAutoWo->id})\n";

    // ═══ SCENARIO 7 — QC / NCR / CAPA ═══
    echo "\n▶▶ SCENARIO 7 — QC NCR & CAPA\n";

    $r = go('POST', 'qc/inspections', [
        'stage'           => 'ipqc',
        'inspection_date' => now()->toDateString(),
        'item_id'         => $state->fgId,
        'qty_inspected'   => 50,
    ]);
    $r->assertStatus(201, "7.1 Create IPQC — got {$r->status()}: " . $r->getContent());
    $ipqcUlid = $r->json('data.ulid');
    $ipqcId   = $r->json('data.id');

    // [FIXED: submit-results → results; PATCH; is_conforming boolean; qty_passed/qty_failed required]
    $ra = go('PATCH', "qc/inspections/{$ipqcUlid}/results", [
        'qty_passed' => 40,
        'qty_failed' => 10,
        'results' => [
            ['criterion' => 'Visual contamination', 'is_conforming' => true,  'actual_value' => null],
            ['criterion' => 'Wall thickness',        'is_conforming' => false, 'actual_value' => '1.72mm'],
        ],
    ]);
    $ra->assertStatus(200, "7.1b IPQC results — got {$ra->status()}: " . $ra->getContent());
    expect($ra->json('data.status'))->toBe('failed', '7.1c IPQC should be "failed"');
    echo "  ✅ 7.1 IPQC failed as expected\n";

    $r = go('POST', 'qc/ncrs', [
        'inspection_id' => $ipqcId,
        'severity'      => 'major',
        'title'         => 'Wall thickness below minimum spec — IPQC',
        'description'   => 'IPQC found average wall thickness of 1.72mm — below the 1.80mm minimum.',
    ]);
    $r->assertStatus(201, "7.2 Create NCR — got {$r->status()}: " . $r->getContent());
    $ncrUlid = $r->json('data.ulid');
    $ncrId   = $r->json('data.id');
    echo "  ✅ 7.2 NCR created ulid={$ncrUlid}\n";

    // [FIXED: POST issue-capa → PATCH capa]
    $ra = go('PATCH', "qc/ncrs/{$ncrUlid}/capa", [
        'type'        => 'corrective',
        'description' => 'Re-shim mold to restore cavity depth.',
        'due_date'    => now()->addDays(7)->toDateString(),
    ]);
    $ra->assertStatus(201, "7.3a Issue CAPA — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 7.3a CAPA issued\n";

    sleep(1);
    $capa = DB::table('capa_actions')->where('ncr_id', $ncrId)->first();
    expect($capa)->not->toBeNull('7.3b Auto-CAPA should exist for NCR');
    echo "  ✅ 7.3b CAPA found ulid={$capa->ulid}\n";

    // [FIXED: POST qc/capas/{}/complete → PATCH qc/capa/{}/complete]
    $ra = go('PATCH', "qc/capa/{$capa->ulid}/complete", [
        'resolution' => 'Mold re-shimmed +0.16mm. CMM confirms 1.94mm. Trial units conforming.',
    ]);
    $ra->assertStatus(200, "7.3c Complete CAPA — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 7.3c CAPA completed\n";

    // [FIXED: POST → PATCH]
    go('PATCH', "qc/ncrs/{$ncrUlid}/close")
        ->assertStatus(200, "7.3d Close NCR");
    echo "  ✅ 7.3d NCR closed\n";

    $r = go('POST', 'qc/inspections', [
        'stage'           => 'oqc',
        'inspection_date' => now()->toDateString(),
        'item_id'         => $state->fgId,
        'qty_inspected'   => 200,
    ]);
    $r->assertStatus(201, "7.4 Create OQC — got {$r->status()}: " . $r->getContent());
    $oqcUlid = $r->json('data.ulid');

    // [FIXED: submit-results → results; PATCH; is_conforming boolean; qty_passed/qty_failed required]
    $rr = go('PATCH', "qc/inspections/{$oqcUlid}/results", [
        'qty_passed' => 200,
        'qty_failed' => 0,
        'results' => [
            ['criterion' => 'Dimensional check', 'is_conforming' => true, 'actual_value' => '111mm'],
            ['criterion' => 'Wall thickness',    'is_conforming' => true, 'actual_value' => '2.1mm'],
            ['criterion' => 'Lid fit',           'is_conforming' => true],
            ['criterion' => 'Visual',            'is_conforming' => true],
        ],
    ]);
    $rr->assertStatus(200, "7.4b OQC results — got {$rr->status()}: " . $rr->getContent());
    expect($rr->json('data.status'))->toBe('passed', '7.4c OQC should be "passed"');
    echo "  ✅ 7.4 OQC passed\n";

    // ═══ SCENARIO 8 — DELIVERY & SHIPMENT ═══
    echo "\n▶▶ SCENARIO 8 — Delivery & Shipment\n";

    sleep(1);
    $autoDr = DB::table('delivery_receipts')
        ->where('direction', 'outbound')
        ->first();

    if ($autoDr) {
        $rc = go('PATCH', "delivery/receipts/{$autoDr->ulid}/confirm");
        $rc->assertStatus(200, "8.1 Confirm auto-DR — got {$rc->status()}: " . $rc->getContent());
        $drUlid = $autoDr->ulid;
        echo "  ✅ 8.1 Auto-DR confirmed ulid={$drUlid}\n";
    } else {
        $r = go('POST', 'delivery/receipts', [
            'direction'    => 'outbound',
            'customer_id'  => $state->customerId,
            'receipt_date' => now()->toDateString(),
            'items'        => [['item_id' => $state->fgId, 'qty_expected' => 10000, 'qty_received' => 10000, 'unit_of_measure' => 'pcs']],
        ]);
        $r->assertStatus(201, "8.1 Create DR — got {$r->status()}: " . $r->getContent());
        $drUlid = $r->json('data.ulid');
        go('PATCH', "delivery/receipts/{$drUlid}/confirm")
            ->assertStatus(200, "8.1b Confirm DR");
        echo "  ✅ 8.1 DR created + confirmed (manual)\n";
    }

    // Guide §8 sanity: 10,007 FG produced − 10,000 delivered = ~7 remaining
    $fgAfterDelivery = (float) DB::table('stock_balances')->where('item_id', $state->fgId)->sum('quantity_on_hand');
    expect($fgAfterDelivery)->toBeLessThanOrEqual(10, "8.1b FG stock after delivery should drop to ~7, got {$fgAfterDelivery}");
    echo "  ✅ 8.1b FG stock after delivery = {$fgAfterDelivery} pcs\n";

    $drId = DB::table('delivery_receipts')->where('ulid', $drUlid)->value('id');
    $r = go('POST', 'delivery/shipments', [
        'delivery_receipt_id' => $drId,
        'carrier'             => 'JRS Express',
        'tracking_number'     => 'JRS-2026-032801',
        'shipped_at'          => now()->toDateString(),
        'estimated_arrival'   => now()->addDay()->toDateString(),
    ]);
    $r->assertStatus(201, "8.2 Create shipment — got {$r->status()}: " . $r->getContent());
    $shipUlid = $r->json('data.ulid');
    echo "  ✅ 8.2 Shipment ulid={$shipUlid}\n";

    go('PATCH', "delivery/shipments/{$shipUlid}/status", ['status' => 'in_transit'])
        ->assertStatus(200, '8.2b Shipment in_transit');
    echo "  ✅ 8.2b Shipment status → in_transit\n";

    go('PATCH', "delivery/shipments/{$shipUlid}/status", ['status' => 'delivered'])
        ->assertStatus(200, '8.2c Shipment delivered');
    echo "  ✅ 8.2c Shipment status → delivered\n";

    // ═══ SCENARIO 9 — AR INVOICE & PAYMENT ═══
    echo "\n▶▶ SCENARIO 9 — AR Invoice & Payment\n";

    $periodId  = DB::table('fiscal_periods')
        ->whereRaw("date_from <= ?", [now()->toDateString()])
        ->whereRaw("date_to >= ?", [now()->subDays(60)->toDateString()])
        ->value('id');
    $arAccId   = DB::table('chart_of_accounts')->whereRaw("LOWER(name) LIKE '%receivable%'")->value('id');
    $revAccId  = DB::table('chart_of_accounts')->where('account_type', 'REVENUE')->first()?->id;
    $cashAccId = DB::table('chart_of_accounts')->where('code', '1001')->value('id');

    // [FIXED: or_number required when vat_amount > 0 (VAT-001)]
    $r = go('POST', 'ar/invoices', [
        'customer_id'        => $state->customerId,
        'fiscal_period_id'   => $periodId,
        'ar_account_id'      => $arAccId,
        'revenue_account_id' => $revAccId,
        'invoice_date'       => now()->toDateString(),
        'due_date'           => now()->addDays(30)->toDateString(),
        'subtotal'           => 280000,
        'vat_amount'         => 33600,
        'or_number'          => 'OR-2026-001',
        'description'        => 'Plastic Container 500ml × 10,000 units @ ₱28.00',
    ]);
    $r->assertStatus(201, "9.1 Create AR invoice — got {$r->status()}: " . $r->getContent());
    $arInvUlid = $r->json('data.ulid');
    echo "  ✅ 9.1 AR invoice ulid={$arInvUlid}\n";

    // [FIXED: POST → PATCH]
    go('PATCH', "ar/invoices/{$arInvUlid}/approve")
        ->assertStatus(200, "9.1b Approve AR invoice");
    echo "  ✅ 9.1b Approved\n";

    // [FIXED: receive-payment → payments; cash_account_id + ar_account_id required; reference → reference_number]
    $rb = go('POST', "ar/invoices/{$arInvUlid}/payments", [
        'amount'           => 313600,
        'payment_date'     => now()->toDateString(),
        'reference_number' => 'BDO-TRF-20260405-001',
        'cash_account_id'  => $cashAccId,
        'ar_account_id'    => $arAccId,
        'payment_method'   => 'bank_transfer',
    ]);
    $rb->assertStatus(201, "9.2 Receive AR payment — got {$rb->status()}: " . $rb->getContent());
    echo "  ✅ 9.2 AR payment received (₱313,600)\n";

    // ═══ SCENARIO 10 — ACCOUNTING JE & REPORTS ═══
    echo "\n▶▶ SCENARIO 10 — Accounting JE & Reports\n";

    // [FIXED: account_type 'EXPENSE' does not exist — use 'OPEX'; debit/credit are separate keys not type+amount]
    $debitAccId  = DB::table('chart_of_accounts as c')
        ->whereNotExists(fn ($q) => $q->from('chart_of_accounts as ch')->whereColumn('ch.parent_id', 'c.id'))
        ->where('c.account_type', 'OPEX')
        ->value('c.id');
    $creditAccId = DB::table('chart_of_accounts as c')
        ->whereNotExists(fn ($q) => $q->from('chart_of_accounts as ch')->whereColumn('ch.parent_id', 'c.id'))
        ->where('c.account_type', 'ASSET')
        ->value('c.id');

    $r = go('POST', 'accounting/journal-entries', [
        'date'        => now()->toDateString(),
        'description' => 'Monthly depreciation — Injection Moulding Machine #1',
        'lines'       => [
            ['account_id' => $debitAccId,  'debit'  => 12500],
            ['account_id' => $creditAccId, 'credit' => 12500],
        ],
    ]);
    $r->assertStatus(201, "10.1 Create JE — got {$r->status()}: " . $r->getContent());
    $jeUlid = $r->json('data.ulid');
    echo "  ✅ 10.1 JE created ulid={$jeUlid}\n";

    // [FIXED: POST → PATCH for submit and post]
    go('PATCH', "accounting/journal-entries/{$jeUlid}/submit")
        ->assertStatus(200, "10.1b Submit JE");
    echo "  ✅ 10.1b JE submitted\n";

    go('PATCH', "accounting/journal-entries/{$jeUlid}/post")
        ->assertStatus(200, "10.1c Post JE");
    echo "  ✅ 10.1c JE posted\n";

    // Guide §10.2: VAT Ledger should reflect output VAT from AR invoice
    go('GET', "tax/vat-ledger?fiscal_period_id={$periodId}")
        ->assertStatus(200, '10.2 VAT Ledger');
    echo "  ✅ 10.2 VAT Ledger 200 OK\n";

    // [FIXED: reports need date_from/date_to (trial-balance, income-statement) or as_of_date (balance-sheet); fiscal_period_id not accepted]
    $period = DB::table('fiscal_periods')->where('id', $periodId)->first();
    $rptFrom = $period?->date_from ?? now()->startOfMonth()->toDateString();
    $rptTo   = $period?->date_to   ?? now()->toDateString();

    // Guide §10.4: GL drilldown for AP (2001) and AR (3001)
    $apAccId   = DB::table('chart_of_accounts')->where('code', '2001')->value('id');
    $arAccIdGl = DB::table('chart_of_accounts')->where('code', '3001')->value('id');
    go('GET', "accounting/reports/gl?account_id={$apAccId}&date_from={$rptFrom}&date_to={$rptTo}")
        ->assertStatus(200, '10.4 GL AP account drilldown');
    echo "  ✅ 10.4 GL AP account drilldown 200 OK\n";
    go('GET', "accounting/reports/gl?account_id={$arAccIdGl}&date_from={$rptFrom}&date_to={$rptTo}")
        ->assertStatus(200, '10.4 GL AR account drilldown');
    echo "  ✅ 10.4 GL AR account drilldown 200 OK\n";

    go('GET', "accounting/reports/trial-balance?date_from={$rptFrom}&date_to={$rptTo}")
        ->assertStatus(200, '10.3 Trial balance');
    echo "  ✅ 10.3 Trial balance 200 OK\n";

    go('GET', "accounting/reports/income-statement?date_from={$rptFrom}&date_to={$rptTo}")
        ->assertStatus(200, '10.5 Income statement');
    echo "  ✅ 10.5 Income statement 200 OK\n";

    go('GET', 'accounting/reports/balance-sheet?as_of_date=' . now()->toDateString())
        ->assertStatus(200, '10.6 Balance sheet');
    echo "  ✅ 10.6 Balance sheet 200 OK\n";

    // ═══ SCENARIO 11 — ISO ═══
    echo "\n▶▶ SCENARIO 11 — ISO\n";

    // [FIXED: version → current_version]
    $r = go('POST', 'iso/documents', [
        'title'           => 'Incoming Material Inspection Procedure Rev. 3',
        'document_type'   => 'procedure',
        'category'        => 'Quality',
        'current_version' => '3.0',
        'effective_date'  => now()->toDateString(),
        'review_date'     => now()->addYear()->toDateString(),
    ]);
    $r->assertStatus(201, "11.1 Create ISO document — got {$r->status()}: " . $r->getContent());
    $docUlid = $r->json('data.ulid');
    echo "  ✅ 11.1 ISO document ulid={$docUlid}\n";

    go('PATCH', "iso/documents/{$docUlid}/submit-for-review")
        ->assertStatus(200, '11.1b ISO doc submit-for-review');
    echo "  ✅ 11.1b ISO document submitted for review\n";

    go('PATCH', "iso/documents/{$docUlid}/approve")
        ->assertStatus(200, '11.1c ISO doc approve');
    echo "  ✅ 11.1c ISO document approved\n";

    $r = go('POST', 'iso/audits', [
        'standard'    => 'ISO 9001:2015',
        'audit_scope' => 'Production Department — Process Compliance (Clauses 8.5.1, 8.5.2, 8.6)',
        'audit_date'  => now()->toDateString(),
    ]);
    $r->assertStatus(201, "11.2 Create audit — got {$r->status()}: " . $r->getContent());
    $auditUlid = $r->json('data.ulid');
    $auditId   = $r->json('data.id');
    echo "  ✅ 11.2 Audit ulid={$auditUlid}\n";

    // [FIXED: POST → PATCH for start and complete]
    go('PATCH', "iso/audits/{$auditUlid}/start")
        ->assertStatus(200, "11.2b Start audit");
    echo "  ✅ 11.2b Audit started\n";

    go('PATCH', "iso/audits/{$auditUlid}/complete")
        ->assertStatus(200, "11.2c Complete audit");
    echo "  ✅ 11.2c Audit completed\n";

    // [FIXED: standalone iso/audit-findings → nested iso/audits/{audit}/findings]
    // [FIXED: fields: audit_id/clause/title removed; finding_type/clause_ref required]
    $r = go('POST', "iso/audits/{$auditUlid}/findings", [
        'finding_type' => 'nonconformity',
        'clause_ref'   => '8.5.1',
        'description'  => 'Labelling SOP not followed for 3 of 10 sampled containers.',
        'severity'     => 'minor',
    ]);
    $r->assertStatus(201, "11.3 Add finding — got {$r->status()}: " . $r->getContent());
    $findingUlid = $r->json('data.ulid');
    $findingId   = $r->json('data.id');
    echo "  ✅ 11.3 Audit finding ulid={$findingUlid}\n";

    sleep(2);
    $autoCapa = DB::table('capa_actions')->where('audit_finding_id', $findingId)->first();
    expect($autoCapa)->not->toBeNull('11.3b Auto-CAPA should be created for audit finding');
    echo "  ✅ 11.3b Auto-CAPA ulid={$autoCapa->ulid}\n";

    // [FIXED: PATCH qc/capa/{ulid}/complete]
    $ra = go('PATCH', "qc/capa/{$autoCapa->ulid}/complete", [
        'resolution' => 'SOP re-briefed to operators; labelling station checklist added.',
    ]);
    $ra->assertStatus(200, "11.3c Complete CAPA — got {$ra->status()}: " . $ra->getContent());
    echo "  ✅ 11.3c CAPA completed\n";

    go('PATCH', "iso/audit-findings/{$findingUlid}/close")
        ->assertStatus(200, '11.3d Close audit finding');
    echo "  ✅ 11.3d Audit finding closed\n";

    echo "\n\n✅✅✅  ALL SCENARIOS COMPLETE — ALL ROUTES IMPLEMENTED  ✅✅✅\n\n";
});
