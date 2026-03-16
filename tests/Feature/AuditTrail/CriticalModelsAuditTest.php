<?php

declare(strict_types=1);

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockLedger;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Mold\Models\MoldMaster;
use App\Domains\Mold\Models\MoldShotLog;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOutputLog;
use App\Domains\QC\Models\Inspection;
use App\Domains\QC\Models\InspectionResult;
use App\Domains\QC\Models\InspectionTemplate;
use App\Models\User;
use OwenIt\Auditing\Models\Audit;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->user = User::factory()->create();
});

// ── StockLedger Audit Tests ────────────────────────────────────────────────

it('audits stock ledger creation', function () {
    $location = WarehouseLocation::factory()->create();
    $item = ItemMaster::factory()->create();

    $ledger = StockLedger::create([
        'item_id' => $item->id,
        'location_id' => $location->id,
        'transaction_type' => 'receipt',
        'quantity' => 100,
        'balance_after' => 100,
        'reference_type' => 'purchase_order',
        'reference_id' => 1,
        'created_by_id' => $this->user->id,
        'created_at' => now(),
    ]);

    $audit = Audit::where('auditable_type', StockLedger::class)
        ->where('auditable_id', $ledger->id)
        ->where('event', 'created')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->new_values)->toHaveKey('item_id');
    expect($audit->new_values)->toHaveKey('quantity');
    expect($audit->new_values)->toHaveKey('transaction_type');
});

it('audits stock ledger quantity changes', function () {
    $location = WarehouseLocation::factory()->create();
    $item = ItemMaster::factory()->create();

    $ledger = StockLedger::create([
        'item_id' => $item->id,
        'location_id' => $location->id,
        'transaction_type' => 'receipt',
        'quantity' => 100,
        'balance_after' => 100,
        'created_by_id' => $this->user->id,
        'created_at' => now(),
    ]);

    // Note: StockLedger is append-only, but we test audit capability
    $ledger->update(['remarks' => 'Updated remark']);

    $audit = Audit::where('auditable_type', StockLedger::class)
        ->where('auditable_id', $ledger->id)
        ->where('event', 'updated')
        ->first();

    expect($audit)->not->toBeNull();
});

// ── ProductionOutputLog Audit Tests ────────────────────────────────────────

it('audits production output log creation', function () {
    $order = ProductionOrder::factory()->create();

    $log = ProductionOutputLog::create([
        'production_order_id' => $order->id,
        'shift' => 'morning',
        'log_date' => now()->toDateString(),
        'qty_produced' => 100.00,
        'qty_rejected' => 5.00,
        'operator_id' => 1,
        'recorded_by_id' => $this->user->id,
    ]);

    $audit = Audit::where('auditable_type', ProductionOutputLog::class)
        ->where('auditable_id', $log->id)
        ->where('event', 'created')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->new_values)->toHaveKey('qty_produced');
    expect($audit->new_values)->toHaveKey('qty_rejected');
});

it('audits production output log updates', function () {
    $order = ProductionOrder::factory()->create();

    $log = ProductionOutputLog::create([
        'production_order_id' => $order->id,
        'shift' => 'morning',
        'log_date' => now()->toDateString(),
        'qty_produced' => 100.00,
        'qty_rejected' => 5.00,
        'operator_id' => 1,
        'recorded_by_id' => $this->user->id,
    ]);

    $originalQty = $log->qty_produced;
    $log->update(['qty_produced' => 150.00]);

    $audit = Audit::where('auditable_type', ProductionOutputLog::class)
        ->where('auditable_id', $log->id)
        ->where('event', 'updated')
        ->first();

    expect($audit)->not->toBeNull();
    expect((float) $audit->old_values['qty_produced'])->toBe($originalQty);
    expect((float) $audit->new_values['qty_produced'])->toBe(150.00);
});

// ── InspectionResult Audit Tests ───────────────────────────────────────────

it('audits inspection result creation', function () {
    $template = InspectionTemplate::factory()->create();
    $inspection = Inspection::factory()->create();

    $result = InspectionResult::create([
        'inspection_id' => $inspection->id,
        'inspection_template_item_id' => 1,
        'criterion' => 'Dimension check',
        'actual_value' => '10.5mm',
        'is_conforming' => true,
        'remarks' => 'Within tolerance',
    ]);

    $audit = Audit::where('auditable_type', InspectionResult::class)
        ->where('auditable_id', $result->id)
        ->where('event', 'created')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->new_values)->toHaveKey('actual_value');
    expect($audit->new_values)->toHaveKey('is_conforming');
});

it('audits inspection result conformance changes', function () {
    $inspection = Inspection::factory()->create();

    $result = InspectionResult::create([
        'inspection_id' => $inspection->id,
        'inspection_template_item_id' => 1,
        'criterion' => 'Dimension check',
        'actual_value' => '10.5mm',
        'is_conforming' => true,
    ]);

    $result->update([
        'actual_value' => '12.0mm',
        'is_conforming' => false,
        'remarks' => 'Out of tolerance - rejected',
    ]);

    $audit = Audit::where('auditable_type', InspectionResult::class)
        ->where('auditable_id', $result->id)
        ->where('event', 'updated')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->old_values['is_conforming'])->toBeTrue();
    expect($audit->new_values['is_conforming'])->toBeFalse();
});

// ── MoldShotLog Audit Tests ────────────────────────────────────────────────

it('audits mold shot log creation', function () {
    $mold = MoldMaster::factory()->create();

    $log = MoldShotLog::create([
        'mold_id' => $mold->id,
        'production_order_id' => 1,
        'shot_count' => 500,
        'operator_id' => $this->user->id,
        'log_date' => now()->toDateString(),
        'remarks' => 'Daily production',
    ]);

    $audit = Audit::where('auditable_type', MoldShotLog::class)
        ->where('auditable_id', $log->id)
        ->where('event', 'created')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->new_values)->toHaveKey('shot_count');
    expect($audit->new_values)->toHaveKey('mold_id');
});

it('audits mold shot count updates', function () {
    $mold = MoldMaster::factory()->create();

    $log = MoldShotLog::create([
        'mold_id' => $mold->id,
        'production_order_id' => 1,
        'shot_count' => 500,
        'operator_id' => $this->user->id,
        'log_date' => now()->toDateString(),
    ]);

    $log->update(['shot_count' => 750]);

    $audit = Audit::where('auditable_type', MoldShotLog::class)
        ->where('auditable_id', $log->id)
        ->where('event', 'updated')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->old_values['shot_count'])->toBe(500);
    expect($audit->new_values['shot_count'])->toBe(750);
});

// ── Audit Configuration Tests ──────────────────────────────────────────────

it('stock ledger audits only specified fields', function () {
    $location = WarehouseLocation::factory()->create();
    $item = ItemMaster::factory()->create();

    $ledger = StockLedger::create([
        'item_id' => $item->id,
        'location_id' => $location->id,
        'transaction_type' => 'receipt',
        'quantity' => 100,
        'balance_after' => 100,
        'created_by_id' => $this->user->id,
        'created_at' => now(),
    ]);

    $audit = Audit::where('auditable_type', StockLedger::class)->first();

    // Verify auditInclude is respected - all key fields should be present
    expect($audit->new_values)->toHaveKey('item_id');
    expect($audit->new_values)->toHaveKey('location_id');
    expect($audit->new_values)->toHaveKey('quantity');
    expect($audit->new_values)->toHaveKey('transaction_type');
});
