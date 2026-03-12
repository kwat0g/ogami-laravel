<?php

declare(strict_types=1);

use App\Http\Controllers\Procurement\GoodsReceiptController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\PurchaseRequestController;
use App\Http\Controllers\Procurement\VendorRfqController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement Routes — /api/v1/procurement
|--------------------------------------------------------------------------
| Approval chain: Staff → Head (note) → Manager (check) → Officer (review) → VP
| SoD: each consecutive stage enforced at DB + service + policy layers
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Purchase Requests ────────────────────────────────────────────────────
    Route::prefix('purchase-requests')->name('purchase-requests.')->group(function () {
        Route::get('/', [PurchaseRequestController::class, 'index'])
            ->name('index');

        Route::post('/', [PurchaseRequestController::class, 'store'])
            ->middleware('throttle:api-action')
            ->name('store');

        Route::get('/{purchaseRequest}', [PurchaseRequestController::class, 'show'])
            ->name('show');

        Route::patch('/{purchaseRequest}', [PurchaseRequestController::class, 'update'])
            ->middleware('throttle:api-action')
            ->name('update');

        // Workflow transitions
        Route::post('/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])
            ->middleware('throttle:api-action')
            ->name('submit');

        Route::post('/{purchaseRequest}/note', [PurchaseRequestController::class, 'note'])
            ->middleware('throttle:api-action')
            ->name('note');

        Route::post('/{purchaseRequest}/check', [PurchaseRequestController::class, 'check'])
            ->middleware('throttle:api-action')
            ->name('check');

        Route::post('/{purchaseRequest}/review', [PurchaseRequestController::class, 'review'])
            ->middleware('throttle:api-action')
            ->name('review');

        Route::post('/{purchaseRequest}/vp-approve', [PurchaseRequestController::class, 'vpApprove'])
            ->middleware('throttle:api-action')
            ->name('vp-approve');

        Route::post('/{purchaseRequest}/budget-check', [PurchaseRequestController::class, 'budgetCheck'])
            ->middleware('throttle:api-action')
            ->name('budget-check');

        Route::post('/{purchaseRequest}/return', [PurchaseRequestController::class, 'returnForRevision'])
            ->middleware('throttle:api-action')
            ->name('return');

        Route::post('/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])
            ->middleware('throttle:api-action')
            ->name('reject');

        Route::post('/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])
            ->middleware('throttle:api-action')
            ->name('cancel');

        Route::get('/{purchaseRequest}/pdf', [PurchaseRequestController::class, 'pdf'])
            ->name('pdf');
    });

    // ── Purchase Orders ──────────────────────────────────────────────────────
    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])
            ->name('index');

        Route::post('/', [PurchaseOrderController::class, 'store'])
            ->middleware('throttle:api-action')
            ->name('store');

        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->name('show');

        Route::patch('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
            ->middleware('throttle:api-action')
            ->name('update');

        Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])
            ->middleware('throttle:api-action')
            ->name('send');

        Route::post('/{purchaseOrder}/assign-vendor', [PurchaseOrderController::class, 'assignVendor'])
            ->middleware('throttle:api-action')
            ->name('assign-vendor');

        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->middleware('throttle:api-action')
            ->name('cancel');
    });

    // ── Goods Receipts ───────────────────────────────────────────────────────
    Route::prefix('goods-receipts')->name('goods-receipts.')->group(function () {
        Route::get('/', [GoodsReceiptController::class, 'index'])
            ->name('index');

        Route::post('/', [GoodsReceiptController::class, 'store'])
            ->middleware('throttle:api-action')
            ->name('store');

        Route::get('/{goodsReceipt}', [GoodsReceiptController::class, 'show'])
            ->name('show');

        Route::post('/{goodsReceipt}/confirm', [GoodsReceiptController::class, 'confirm'])
            ->middleware('throttle:api-action')
            ->name('confirm');

        Route::delete('/{goodsReceipt}', [GoodsReceiptController::class, 'destroy'])
            ->middleware('throttle:api-action')
            ->name('destroy');
    });

    // ── Vendor RFQs ──────────────────────────────────────────────────────────
    Route::prefix('rfqs')->name('rfqs.')->group(function () {
        Route::get('/', [VendorRfqController::class, 'index'])->name('index');

        Route::post('/', [VendorRfqController::class, 'store'])
            ->middleware('throttle:api-action')
            ->name('store');

        Route::get('/{vendorRfq}', [VendorRfqController::class, 'show'])->name('show');

        Route::post('/{vendorRfq}/send', [VendorRfqController::class, 'send'])
            ->middleware('throttle:api-action')
            ->name('send');

        Route::post('/{vendorRfq}/vendors/{vendor}/quote', [VendorRfqController::class, 'receiveQuote'])
            ->middleware('throttle:api-action')
            ->name('receive-quote');

        Route::post('/{vendorRfq}/vendors/{vendor}/decline', [VendorRfqController::class, 'recordDecline'])
            ->middleware('throttle:api-action')
            ->name('record-decline');

        Route::post('/{vendorRfq}/close', [VendorRfqController::class, 'close'])
            ->middleware('throttle:api-action')
            ->name('close');

        Route::post('/{vendorRfq}/cancel', [VendorRfqController::class, 'cancel'])
            ->middleware('throttle:api-action')
            ->name('cancel');
    });

    // ── Procurement Analytics ────────────────────────────────────────────────
    Route::get('reports/analytics', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
        $year = $request->integer('year', now()->year);

        // Spend by vendor (top 15)
        $byVendor = \Illuminate\Support\Facades\DB::table('purchase_orders')
            ->join('vendors', 'purchase_orders.vendor_id', '=', 'vendors.id')
            ->whereIn('purchase_orders.status', ['approved', 'partially_received', 'received', 'completed'])
            ->whereYear('purchase_orders.created_at', $year)
            ->select('vendors.company_name as vendor', \Illuminate\Support\Facades\DB::raw('sum(purchase_orders.total_amount) as total_spend'))
            ->groupBy('vendors.company_name')
            ->orderByDesc('total_spend')
            ->limit(15)
            ->get();

        // Spend by item category
        $byCategory = \Illuminate\Support\Facades\DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->join('item_masters', 'purchase_order_items.item_id', '=', 'item_masters.id')
            ->leftJoin('item_categories', 'item_masters.category_id', '=', 'item_categories.id')
            ->whereIn('purchase_orders.status', ['approved', 'partially_received', 'received', 'completed'])
            ->whereYear('purchase_orders.created_at', $year)
            ->select(
                \Illuminate\Support\Facades\DB::raw("coalesce(item_categories.name, 'Uncategorized') as category"),
                \Illuminate\Support\Facades\DB::raw('sum(purchase_order_items.quantity * purchase_order_items.unit_price) as total_spend'),
            )
            ->groupBy('category')
            ->orderByDesc('total_spend')
            ->get();

        // Summary stats
        $totalPOs = \Illuminate\Support\Facades\DB::table('purchase_orders')
            ->whereIn('status', ['approved', 'partially_received', 'received', 'completed'])
            ->whereYear('created_at', $year)->count();
        $totalSpend = \Illuminate\Support\Facades\DB::table('purchase_orders')
            ->whereIn('status', ['approved', 'partially_received', 'received', 'completed'])
            ->whereYear('created_at', $year)->sum('total_amount');
        $avgPoValue = $totalPOs > 0 ? round((float) $totalSpend / $totalPOs, 2) : 0;
        $activeVendors = \Illuminate\Support\Facades\DB::table('purchase_orders')
            ->whereIn('status', ['approved', 'partially_received', 'received', 'completed'])
            ->whereYear('created_at', $year)->distinct('vendor_id')->count('vendor_id');

        return response()->json([
            'by_vendor'   => $byVendor,
            'by_category' => $byCategory,
            'summary'     => [
                'total_pos'      => $totalPOs,
                'total_spend'    => round((float) $totalSpend, 2),
                'avg_po_value'   => $avgPoValue,
                'active_vendors' => $activeVendors,
            ],
            'year' => $year,
        ]);
    })->name('reports.analytics');
});
