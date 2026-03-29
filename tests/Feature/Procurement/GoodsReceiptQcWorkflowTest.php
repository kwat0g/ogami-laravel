<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\QC\Models\Inspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
});

/**
 * Helper: create a confirmed PO with one item that requires IQC, and a draft GR.
 */
function createGrWithIqcItem(): array
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    // Ensure an item category exists for resolveItemMasters
    $category = ItemCategory::firstOrCreate(
        ['name' => 'Raw Materials'],
        ['code' => 'RM', 'description' => 'Raw materials'],
    );

    $item = ItemMaster::create([
        'item_code' => 'TEST-IQC-001',
        'name' => 'IQC Test Material',
        'unit_of_measure' => 'kg',
        'type' => 'raw_material',
        'requires_iqc' => true,
        'is_active' => true,
        'category_id' => $category->id,
    ]);

    // Create PO in receivable status
    $po = PurchaseOrder::factory()->create([
        'status' => 'acknowledged',
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'item_description' => 'IQC Test Material',
        'item_master_id' => $item->id,
        'quantity_ordered' => 100,
        'unit_of_measure' => 'kg',
        'unit_cost' => 50.00,
        'agreed_unit_cost' => 50.00,
        'quantity_received' => 0,
    ]);

    // Create GR sequence if not exists
    DB::statement('CREATE SEQUENCE IF NOT EXISTS goods_receipt_seq START 1');

    $gr = GoodsReceipt::create([
        'gr_reference' => 'GR-TEST-' . now()->format('Y-m') . '-00001',
        'purchase_order_id' => $po->id,
        'received_by_id' => $user->id,
        'received_date' => now()->toDateString(),
        'status' => 'draft',
        'three_way_match_passed' => false,
        'ap_invoice_created' => false,
    ]);

    GoodsReceiptItem::create([
        'goods_receipt_id' => $gr->id,
        'po_item_id' => $poItem->id,
        'item_master_id' => $item->id,
        'quantity_received' => 100,
        'unit_of_measure' => 'kg',
        'condition' => 'good',
    ]);

    return compact('user', 'po', 'poItem', 'item', 'gr');
}

// ── Submit for QC ────────────────────────────────────────────────────────────

test('submit for QC transitions draft GR to pending_qc', function () {
    $ctx = createGrWithIqcItem();

    $response = $this->actingAs($ctx['user'])
        ->postJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}/submit-for-qc");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'pending_qc');
    expect($ctx['gr']->fresh()->status)->toBe('pending_qc');
    expect($ctx['gr']->fresh()->submitted_for_qc_by_id)->toBe($ctx['user']->id);
    expect($ctx['gr']->fresh()->submitted_for_qc_at)->not->toBeNull();
});

test('submit for QC is blocked when GR is not in draft', function () {
    $ctx = createGrWithIqcItem();
    $ctx['gr']->update(['status' => 'pending_qc']);

    $response = $this->actingAs($ctx['user'])
        ->postJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}/submit-for-qc");

    $response->assertStatus(403);
});

// ── Confirm blocked from draft ───────────────────────────────────────────────

test('confirm is blocked from draft status — must go through QC first', function () {
    $ctx = createGrWithIqcItem();

    $response = $this->actingAs($ctx['user'])
        ->postJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}/confirm");

    // Policy denies: draft is not qc_passed or partial_accept
    $response->assertStatus(403);
});

// ── Confirm allowed from qc_passed ───────────────────────────────────────────

test('confirm is allowed from qc_passed status', function () {
    $ctx = createGrWithIqcItem();
    $ctx['gr']->update([
        'status' => 'qc_passed',
        'qc_result' => 'passed',
        'qc_completed_at' => now(),
    ]);

    // Set item accepted quantities
    $ctx['gr']->items()->update([
        'qc_status' => 'passed',
        'quantity_accepted' => 100,
        'quantity_rejected' => 0,
    ]);

    $response = $this->actingAs($ctx['user'])
        ->postJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}/confirm");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'confirmed');
});

// ── Reject from various states ───────────────────────────────────────────────

test('reject is allowed from draft status', function () {
    $ctx = createGrWithIqcItem();

    $response = $this->actingAs($ctx['user'])
        ->postJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}/reject", [
            'reason' => 'Wrong items delivered',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'rejected');
});

test('reject is allowed from pending_qc status', function () {
    $ctx = createGrWithIqcItem();
    $ctx['gr']->update(['status' => 'pending_qc']);

    $response = $this->actingAs($ctx['user'])
        ->postJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}/reject", [
            'reason' => 'QC found critical defects',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'rejected');
});

test('reject is allowed from qc_failed status', function () {
    $ctx = createGrWithIqcItem();
    $ctx['gr']->update(['status' => 'qc_failed', 'qc_result' => 'failed']);

    $response = $this->actingAs($ctx['user'])
        ->postJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}/reject", [
            'reason' => 'Defects too severe to accept',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'rejected');
});

// ── Accept with defects ──────────────────────────────────────────────────────

test('accept with defects transitions qc_failed to partial_accept', function () {
    $ctx = createGrWithIqcItem();
    $ctx['gr']->update(['status' => 'qc_failed', 'qc_result' => 'failed']);

    $grItem = $ctx['gr']->items->first();

    $response = $this->actingAs($ctx['user'])
        ->postJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}/accept-with-defects", [
            'items' => [
                [
                    'gr_item_id' => $grItem->id,
                    'quantity_accepted' => 80,
                    'quantity_rejected' => 20,
                    'defect_type' => 'dimensional',
                    'defect_description' => '20 units out of spec',
                ],
            ],
            'notes' => 'Accepting 80/100 units',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'partial_accept');

    $grItem->refresh();
    expect((float) $grItem->quantity_accepted)->toBe(80.0);
    expect((float) $grItem->quantity_rejected)->toBe(20.0);
    expect($grItem->defect_type)->toBe('dimensional');
});

test('accept with defects is blocked when not qc_failed', function () {
    $ctx = createGrWithIqcItem();
    // GR is still draft
    $grItem = $ctx['gr']->items->first();

    $response = $this->actingAs($ctx['user'])
        ->postJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}/accept-with-defects", [
            'items' => [
                [
                    'gr_item_id' => $grItem->id,
                    'quantity_accepted' => 100,
                    'quantity_rejected' => 0,
                ],
            ],
        ]);

    $response->assertStatus(403);
});

// ── QC fields in API response ────────────────────────────────────────────────

test('GR show endpoint includes QC workflow fields', function () {
    $ctx = createGrWithIqcItem();
    $ctx['gr']->update([
        'status' => 'pending_qc',
        'submitted_for_qc_by_id' => $ctx['user']->id,
        'submitted_for_qc_at' => now(),
    ]);

    $response = $this->actingAs($ctx['user'])
        ->getJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}");

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'status',
            'submitted_for_qc_at',
            'qc_result',
            'qc_completed_at',
            'qc_notes',
            'rejection_reason',
            'returned_at',
            'return_reason',
        ],
    ]);
});

// ── Return to Supplier ───────────────────────────────────────────────────────

test('return to supplier is blocked from non-confirmed status', function () {
    $ctx = createGrWithIqcItem();

    $response = $this->actingAs($ctx['user'])
        ->postJson("/api/v1/procurement/goods-receipts/{$ctx['gr']->ulid}/return-to-supplier", [
            'reason' => 'Incorrect items',
        ]);

    $response->assertStatus(403);
});
