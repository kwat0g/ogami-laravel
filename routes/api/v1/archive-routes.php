<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Archive Routes — Archived list, Restore, Force-Delete
|--------------------------------------------------------------------------
| These routes supplement existing module routes with archive operations
| for modules that don't yet have dedicated controller methods.
| All models use SoftDeletes, so onlyTrashed/restore/forceDelete work.
|--------------------------------------------------------------------------
*/

// ── Procurement ─────────────────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'module_access:procurement'])->group(function () {
    // Purchase Requests
    Route::get('procurement/purchase-requests-archived', function (Request $request) {
        return \App\Domains\Procurement\Models\PurchaseRequest::onlyTrashed()
            ->with('department', 'requestedBy')
            ->latest('deleted_at')
            ->paginate($request->integer('per_page', 20));
    })->name('purchase-requests.archived');

    Route::post('procurement/purchase-requests/{id}/restore', function (Request $request, int $id) {
        abort_unless($request->user()?->hasPermissionTo('procurement.purchase_requests.create'), 403, 'Unauthorized');
        $record = \App\Domains\Procurement\Models\PurchaseRequest::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json(['message' => 'Purchase request restored.', 'data' => $record]);
    })->middleware('throttle:api-action')->name('purchase-requests.restore');

    Route::delete('procurement/purchase-requests/{id}/force', function (Request $request, int $id) {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        \App\Domains\Procurement\Models\PurchaseRequest::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Purchase request permanently deleted.']);
    })->middleware('throttle:api-action')->name('purchase-requests.force-delete');

    // Purchase Orders
    Route::get('procurement/purchase-orders-archived', function (Request $request) {
        return \App\Domains\Procurement\Models\PurchaseOrder::onlyTrashed()
            ->with('vendor')
            ->latest('deleted_at')
            ->paginate($request->integer('per_page', 20));
    })->name('purchase-orders.archived');

    Route::post('procurement/purchase-orders/{id}/restore', function (Request $request, int $id) {
        abort_unless($request->user()?->hasPermissionTo('procurement.purchase_orders.create'), 403, 'Unauthorized');
        $record = \App\Domains\Procurement\Models\PurchaseOrder::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json(['message' => 'Purchase order restored.', 'data' => $record]);
    })->middleware('throttle:api-action')->name('purchase-orders.restore');

    Route::delete('procurement/purchase-orders/{id}/force', function (Request $request, int $id) {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        \App\Domains\Procurement\Models\PurchaseOrder::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Purchase order permanently deleted.']);
    })->middleware('throttle:api-action')->name('purchase-orders.force-delete');

    // Goods Receipts
    Route::get('procurement/goods-receipts-archived', function (Request $request) {
        return \App\Domains\Procurement\Models\GoodsReceipt::onlyTrashed()
            ->with('purchaseOrder.vendor')
            ->latest('deleted_at')
            ->paginate($request->integer('per_page', 20));
    })->name('goods-receipts.archived');

    Route::post('procurement/goods-receipts/{id}/restore', function (Request $request, int $id) {
        abort_unless($request->user()?->hasPermissionTo('procurement.goods_receipts.create'), 403, 'Unauthorized');
        $record = \App\Domains\Procurement\Models\GoodsReceipt::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json(['message' => 'Goods receipt restored.', 'data' => $record]);
    })->middleware('throttle:api-action')->name('goods-receipts.restore');

    Route::delete('procurement/goods-receipts/{id}/force', function (Request $request, int $id) {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        \App\Domains\Procurement\Models\GoodsReceipt::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Goods receipt permanently deleted.']);
    })->middleware('throttle:api-action')->name('goods-receipts.force-delete');
});

// ── QC ──────────────────────────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'module_access:qc'])->group(function () {
    // Inspections
    Route::get('qc/inspections-archived', function (Request $request) {
        return \App\Domains\QC\Models\Inspection::onlyTrashed()
            ->with('template', 'inspector')
            ->latest('deleted_at')
            ->paginate($request->integer('per_page', 20));
    })->name('inspections.archived');

    Route::post('qc/inspections/{id}/restore', function (Request $request, int $id) {
        abort_unless($request->user()?->hasPermissionTo('qc.inspections.create'), 403, 'Unauthorized');
        $record = \App\Domains\QC\Models\Inspection::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json(['message' => 'Inspection restored.', 'data' => $record]);
    })->middleware('throttle:api-action')->name('inspections.restore');

    Route::delete('qc/inspections/{id}/force', function (Request $request, int $id) {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        \App\Domains\QC\Models\Inspection::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Inspection permanently deleted.']);
    })->middleware('throttle:api-action')->name('inspections.force-delete');

    // NCRs
    Route::get('qc/ncrs-archived', function (Request $request) {
        return \App\Domains\QC\Models\NonConformanceReport::onlyTrashed()
            ->latest('deleted_at')
            ->paginate($request->integer('per_page', 20));
    })->name('ncrs.archived');

    Route::post('qc/ncrs/{id}/restore', function (Request $request, int $id) {
        abort_unless($request->user()?->hasPermissionTo('qc.ncr.create'), 403, 'Unauthorized');
        $record = \App\Domains\QC\Models\NonConformanceReport::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json(['message' => 'NCR restored.', 'data' => $record]);
    })->middleware('throttle:api-action')->name('ncrs.restore');

    Route::delete('qc/ncrs/{id}/force', function (Request $request, int $id) {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        \App\Domains\QC\Models\NonConformanceReport::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'NCR permanently deleted.']);
    })->middleware('throttle:api-action')->name('ncrs.force-delete');
});

// ── Maintenance — Work Orders ───────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'module_access:maintenance'])->group(function () {
    Route::get('maintenance/work-orders-archived', function (Request $request) {
        return \App\Domains\Maintenance\Models\MaintenanceWorkOrder::onlyTrashed()
            ->with('equipment')
            ->latest('deleted_at')
            ->paginate($request->integer('per_page', 20));
    })->name('work-orders.archived');

    Route::post('maintenance/work-orders/{id}/restore', function (Request $request, int $id) {
        abort_unless($request->user()?->hasPermissionTo('maintenance.work_orders.create'), 403, 'Unauthorized');
        $record = \App\Domains\Maintenance\Models\MaintenanceWorkOrder::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json(['message' => 'Work order restored.', 'data' => $record]);
    })->middleware('throttle:api-action')->name('work-orders.restore');

    Route::delete('maintenance/work-orders/{id}/force', function (Request $request, int $id) {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        \App\Domains\Maintenance\Models\MaintenanceWorkOrder::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Work order permanently deleted.']);
    })->middleware('throttle:api-action')->name('work-orders.force-delete');
});

// ── Delivery ────────────────────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'module_access:delivery'])->group(function () {
    Route::get('delivery/receipts-archived', function (Request $request) {
        return \App\Domains\Delivery\Models\DeliveryReceipt::onlyTrashed()
            ->latest('deleted_at')
            ->paginate($request->integer('per_page', 20));
    })->name('delivery-receipts.archived');

    Route::post('delivery/receipts/{id}/restore', function (Request $request, int $id) {
        abort_unless($request->user()?->hasPermissionTo('delivery.manage'), 403, 'Unauthorized');
        $record = \App\Domains\Delivery\Models\DeliveryReceipt::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json(['message' => 'Delivery receipt restored.', 'data' => $record]);
    })->middleware('throttle:api-action')->name('delivery-receipts.restore');

    Route::delete('delivery/receipts/{id}/force', function (Request $request, int $id) {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        \App\Domains\Delivery\Models\DeliveryReceipt::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Delivery receipt permanently deleted.']);
    })->middleware('throttle:api-action')->name('delivery-receipts.force-delete');
});

// ── Production — Delivery Schedules ─────────────────────────────────────────

Route::middleware(['auth:sanctum', 'module_access:production'])->group(function () {
    Route::get('production/delivery-schedules-archived', function (Request $request) {
        return \App\Domains\Production\Models\DeliverySchedule::onlyTrashed()
            ->with('customer', 'productItem')
            ->latest('deleted_at')
            ->paginate($request->integer('per_page', 20));
    })->name('delivery-schedules.archived');

    Route::post('production/delivery-schedules/{id}/restore', function (Request $request, int $id) {
        abort_unless($request->user()?->hasPermissionTo('production.orders.create'), 403, 'Unauthorized');
        $record = \App\Domains\Production\Models\DeliverySchedule::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json(['message' => 'Delivery schedule restored.', 'data' => $record]);
    })->middleware('throttle:api-action')->name('delivery-schedules.restore');

    Route::delete('production/delivery-schedules/{id}/force', function (Request $request, int $id) {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        \App\Domains\Production\Models\DeliverySchedule::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Delivery schedule permanently deleted.']);
    })->middleware('throttle:api-action')->name('delivery-schedules.force-delete');
});

// ── Inventory ───────────────────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'module_access:inventory'])->group(function () {
    // Item Masters
    Route::get('inventory/items-archived', function (Request $request) {
        return \App\Domains\Inventory\Models\ItemMaster::onlyTrashed()
            ->with('category')
            ->latest('deleted_at')
            ->paginate($request->integer('per_page', 20));
    })->name('items.archived');

    Route::post('inventory/items/{id}/restore', function (Request $request, int $id) {
        abort_unless($request->user()?->hasPermissionTo('inventory.items.create'), 403, 'Unauthorized');
        $record = \App\Domains\Inventory\Models\ItemMaster::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json(['message' => 'Item restored.', 'data' => $record]);
    })->middleware('throttle:api-action')->name('items.restore');

    Route::delete('inventory/items/{id}/force', function (Request $request, int $id) {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        \App\Domains\Inventory\Models\ItemMaster::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Item permanently deleted.']);
    })->middleware('throttle:api-action')->name('items.force-delete');

    // Material Requisitions
    Route::get('inventory/mrqs-archived', function (Request $request) {
        return \App\Domains\Inventory\Models\MaterialRequisition::onlyTrashed()
            ->latest('deleted_at')
            ->paginate($request->integer('per_page', 20));
    })->name('mrqs.archived');

    Route::post('inventory/mrqs/{id}/restore', function (Request $request, int $id) {
        abort_unless($request->user()?->hasPermissionTo('inventory.mrq.create'), 403, 'Unauthorized');
        $record = \App\Domains\Inventory\Models\MaterialRequisition::onlyTrashed()->findOrFail($id);
        $record->restore();
        return response()->json(['message' => 'Material requisition restored.', 'data' => $record]);
    })->middleware('throttle:api-action')->name('mrqs.restore');

    Route::delete('inventory/mrqs/{id}/force', function (Request $request, int $id) {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        \App\Domains\Inventory\Models\MaterialRequisition::onlyTrashed()->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Material requisition permanently deleted.']);
    })->middleware('throttle:api-action')->name('mrqs.force-delete');
});
