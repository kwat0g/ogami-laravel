<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Http\Controllers\Procurement\GoodsReceiptController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\PurchaseRequestController;
use App\Http\Controllers\Procurement\VendorRfqController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement Routes — /api/v1/procurement
|--------------------------------------------------------------------------
| Approval chain: Staff → Head (note) → Manager (check) → Officer (review) → VP
| SoD: each consecutive stage enforced at DB + service + policy layers
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:procurement'])->group(function () {

    // ── Purchase Requests ────────────────────────────────────────────────────
    Route::prefix('purchase-requests')->name('purchase-requests.')->group(function () {
        Route::get('/', [PurchaseRequestController::class, 'index'])
            ->name('index');

        Route::post('/', [PurchaseRequestController::class, 'store'])
            ->middleware('throttle:api-action')
            ->name('store');

        // Batch operations (must be above parameterised routes)
        Route::post('/batch-review', [PurchaseRequestController::class, 'batchReview'])
            ->middleware('throttle:api-action')
            ->name('batch-review');
        Route::post('/batch-reject', [PurchaseRequestController::class, 'batchReject'])
            ->middleware('throttle:api-action')
            ->name('batch-reject');

        Route::get('/{purchaseRequest}', [PurchaseRequestController::class, 'show'])
            ->name('show');

        Route::patch('/{purchaseRequest}', [PurchaseRequestController::class, 'update'])
            ->middleware('throttle:api-action')
            ->name('update');

        // Workflow transitions with SoD enforcement
        // Simplified 3-stage workflow: Draft → Pending Review → Reviewed → Budget Verified → Approved

        Route::post('/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])
            ->middleware('throttle:api-action')
            ->name('submit');

        // Note and Check endpoints removed - workflow simplified
        // Old: draft → submitted → noted → checked → reviewed → budget_verified → approved
        // New: draft → pending_review → reviewed → budget_verified → approved

        Route::post('/{purchaseRequest}/review', [PurchaseRequestController::class, 'review'])
            ->middleware('throttle:api-action')
            ->name('review');

        Route::post('/{purchaseRequest}/budget-check', [PurchaseRequestController::class, 'budgetCheck'])
            ->middleware('throttle:api-action')
            ->name('budget-check');

        Route::post('/{purchaseRequest}/vp-approve', [PurchaseRequestController::class, 'vpApprove'])
            ->middleware('throttle:api-action')
            ->name('vp-approve');

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

        // Duplicate an existing Purchase Request
        Route::post('/{purchaseRequest}/duplicate', [PurchaseRequestController::class, 'duplicate'])
            ->middleware('throttle:api-action')
            ->name('duplicate');

        // Convert approved Material Requisition to Purchase Request (when stock insufficient)
        Route::post('/from-mrq/{materialRequisition}', [PurchaseRequestController::class, 'createFromMrq'])
            ->middleware('throttle:api-action')
            ->name('create-from-mrq');
    });

    // ── Budget Pre-Check ─────────────────────────────────────────────────────
    // Check if department has sufficient budget before creating PR
    Route::post('/budget-check', function (Request $request): JsonResponse {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.estimated_unit_cost' => ['required', 'numeric', 'gt:0'],
        ]);

        /** @var Department|null $dept */
        $dept = Department::find($validated['department_id']);

        if ($dept === null || $dept->annual_budget_centavos === 0) {
            return response()->json([
                'available' => true,
                'budget' => 0,
                'ytd_spend' => 0,
                'this_pr' => 0,
                'remaining' => 0,
                'message' => 'No budget limit set for this department',
            ]);
        }

        // Calculate fiscal year start
        $startMonth = (int) $dept->fiscal_year_start_month;
        $now = now();
        $fyStart = $now->copy()->month($startMonth)->startOfMonth();
        if ($fyStart->gt($now)) {
            $fyStart->subYear();
        }

        // Calculate YTD spend from budget-verified/approved PRs
        $ytdSpend = (int) PurchaseRequest::where('department_id', $dept->id)
            ->whereIn('status', ['budget_verified', 'approved', 'converted_to_po'])
            ->where('created_at', '>=', $fyStart)
            ->sum('total_estimated_cost');

        // Calculate this PR amount (convert to centavos)
        $thisPrAmount = (int) collect($validated['items'])->sum(
            fn ($item) => ($item['quantity'] ?? 0) * ($item['estimated_unit_cost'] ?? 0) * 100
        );

        $remaining = $dept->annual_budget_centavos - $ytdSpend;
        $available = ($ytdSpend + $thisPrAmount) <= $dept->annual_budget_centavos;

        return response()->json([
            'available' => $available,
            'budget' => $dept->annual_budget_centavos,
            'ytd_spend' => $ytdSpend,
            'this_pr' => $thisPrAmount,
            'remaining' => $remaining,
            'formatted' => [
                'budget' => '₱'.number_format($dept->annual_budget_centavos / 100, 2),
                'ytd_spend' => '₱'.number_format($ytdSpend / 100, 2),
                'this_pr' => '₱'.number_format($thisPrAmount / 100, 2),
                'remaining' => '₱'.number_format($remaining / 100, 2),
            ],
        ]);
    })->middleware('throttle:api')->name('budget-check');

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

        Route::post('/{purchaseOrder}/accept-changes', [PurchaseOrderController::class, 'acceptChanges'])
            ->middleware('throttle:api-action')
            ->name('accept-changes');

        Route::post('/{purchaseOrder}/reject-changes', [PurchaseOrderController::class, 'rejectChanges'])
            ->middleware('throttle:api-action')
            ->name('reject-changes');

        Route::get('/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'pdf'])
            ->name('pdf');
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

        Route::post('/{goodsReceipt}/reject', [GoodsReceiptController::class, 'reject'])
            ->middleware('throttle:api-action')
            ->name('reject');

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

        Route::post('/{vendorRfq}/award/{vendor}', [VendorRfqController::class, 'award'])
            ->middleware('throttle:api-action')
            ->name('award');
    });

    // ── Vendor Suggestion ────────────────────────────────────────────────────
    // Returns top-5 accredited vendors who supply items matching the search term.
    Route::get('items/suggest-vendors', function (Request $request): JsonResponse {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $results = DB::table('vendor_items')
            ->join('vendors', 'vendor_items.vendor_id', '=', 'vendors.id')
            ->where('vendors.is_active', true)
            ->where('vendors.accreditation_status', 'accredited')
            ->where('vendor_items.is_active', true)
            ->whereNull('vendor_items.deleted_at')
            ->whereNull('vendors.deleted_at')
            ->where(function ($sub) use ($q) {
                $sub->whereRaw('LOWER(vendor_items.item_name) LIKE ?', ['%'.mb_strtolower($q).'%'])
                    ->orWhereRaw('LOWER(vendor_items.item_code) LIKE ?', ['%'.mb_strtolower($q).'%']);
            })
            ->select(
                'vendors.id as vendor_id',
                'vendors.name as vendor_name',
                'vendor_items.id as vendor_item_id',
                'vendor_items.item_code',
                'vendor_items.item_name',
                'vendor_items.unit_of_measure',
                'vendor_items.unit_price',
            )
            ->orderBy('vendor_items.unit_price')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'vendor_id' => $row->vendor_id,
                'vendor_name' => $row->vendor_name,
                'vendor_item_id' => $row->vendor_item_id,
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'unit_of_measure' => $row->unit_of_measure,
                'unit_price' => round($row->unit_price / 100, 2),
            ]);

        return response()->json(['data' => $results]);
    })->name('items.suggest-vendors');

    // ── Procurement Analytics ────────────────────────────────────────────────
    Route::get('reports/analytics', function (Request $request): JsonResponse {
        $year = $request->integer('year', now()->year);

        // Spend by vendor (top 15)
        $byVendor = DB::table('purchase_orders')
            ->join('vendors', 'purchase_orders.vendor_id', '=', 'vendors.id')
            ->whereIn('purchase_orders.status', ['sent', 'partially_received', 'fully_received', 'closed'])
            ->whereYear('purchase_orders.created_at', $year)
            ->select('vendors.name as vendor', DB::raw('sum(purchase_orders.total_po_amount) as total_spend'))
            ->groupBy('vendors.name')
            ->orderByDesc('total_spend')
            ->limit(15)
            ->get();

        // Spend by item category
        $byCategory = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->join('item_masters', 'purchase_order_items.item_master_id', '=', 'item_masters.id')
            ->leftJoin('item_categories', 'item_masters.category_id', '=', 'item_categories.id')
            ->whereIn('purchase_orders.status', ['sent', 'partially_received', 'fully_received', 'closed'])
            ->whereYear('purchase_orders.created_at', $year)
            ->select(
                DB::raw("coalesce(item_categories.name, 'Uncategorized') as category"),
                DB::raw('sum(purchase_order_items.total_cost) as total_spend'),
            )
            ->groupBy('category')
            ->orderByDesc('total_spend')
            ->get();

        // Summary stats
        $totalPOs = DB::table('purchase_orders')
            ->whereIn('status', ['sent', 'partially_received', 'fully_received', 'closed'])
            ->whereYear('created_at', $year)->count();
        $totalSpend = DB::table('purchase_orders')
            ->whereIn('status', ['sent', 'partially_received', 'fully_received', 'closed'])
            ->whereYear('created_at', $year)->sum('total_po_amount');
        $avgPoValue = $totalPOs > 0 ? round((float) $totalSpend / $totalPOs, 2) : 0;
        $activeVendors = DB::table('purchase_orders')
            ->whereIn('status', ['sent', 'partially_received', 'fully_received', 'closed'])
            ->whereYear('created_at', $year)->distinct('vendor_id')->count('vendor_id');

        return response()->json([
            'by_vendor' => $byVendor,
            'by_category' => $byCategory,
            'summary' => [
                'total_pos' => $totalPOs,
                'total_spend' => round((float) $totalSpend, 2),
                'avg_po_value' => $avgPoValue,
                'active_vendors' => $activeVendors,
            ],
            'year' => $year,
        ]);
    })->name('reports.analytics');

    // ── Vendor Scorecard ────────────────────────────────────────────────────
    Route::get('vendor-scores', function (\Illuminate\Http\Request $request) {
        $service = app(\App\Domains\Procurement\Services\VendorScoringService::class);
        $year = $request->filled('year') ? $request->integer('year') : null;
        return response()->json(['data' => $service->allVendorScores($year)]);
    })->name('vendor-scores');
});
