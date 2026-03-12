<?php

declare(strict_types=1);

namespace App\Http\Controllers\VendorPortal;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Domains\AP\Services\EwtService;
use App\Domains\AP\Services\VendorFulfillmentService;
use App\Domains\AP\Services\VendorItemService;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Http\Controllers\Controller;
use App\Imports\VendorItemImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * VendorPortalController — vendor-facing endpoints for order management.
 *
 * All routes protected by auth:sanctum + vendor_scope middleware.
 * Vendor users can only access POs belonging to their vendor account.
 */
final class VendorPortalController extends Controller
{
    public function __construct(
        private readonly VendorFulfillmentService $fulfillmentService,
        private readonly VendorItemService $itemService,
        private readonly EwtService $ewtService,
    ) {}

    // ── Orders ───────────────────────────────────────────────────────────────

    /**
     * GET /vendor-portal/orders
     * List POs for the authenticated vendor (status = sent or partially_received).
     */
    public function orders(Request $request): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');

        $query = PurchaseOrder::with(['items', 'purchaseRequest'])
            ->where('vendor_id', $vendorId)
            ->whereIn('status', ['sent', 'partially_received', 'fully_received', 'closed']);

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $orders = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($orders);
    }

    /**
     * GET /vendor-portal/orders/{purchaseOrder}
     * Show a single PO with items and fulfillment history.
     */
    public function orderDetail(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertVendorOwns($purchaseOrder);

        $purchaseOrder->load(['items', 'purchaseRequest', 'fulfillmentNotes.vendorUser', 'goodsReceipts']);

        return response()->json(['data' => $purchaseOrder]);
    }

    /**
     * POST /vendor-portal/orders/{purchaseOrder}/in-transit
     * Notify that goods have been dispatched.
     */
    public function markInTransit(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertVendorOwns($purchaseOrder);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $note = $this->fulfillmentService->markInTransit(
            $purchaseOrder,
            $request->user(),
            $validated['notes'] ?? ''
        );

        return response()->json([
            'message' => 'In-transit notification recorded.',
            'data'    => $note,
        ]);
    }

    /**
     * POST /vendor-portal/orders/{purchaseOrder}/deliver
     * Confirm delivery and auto-create a Goods Receipt draft.
     *
     * Body: { notes?: string, items: [{po_item_id: int, qty_delivered: float}] }
     */
    public function markDelivered(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertVendorOwns($purchaseOrder);

        $validated = $request->validate([
            'notes'                => ['nullable', 'string', 'max:1000'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.po_item_id'   => ['required', 'integer'],
            'items.*.qty_delivered' => ['required', 'numeric', 'min:0.001'],
        ]);

        $note = $this->fulfillmentService->markDelivered(
            $purchaseOrder,
            $request->user(),
            $validated['items'],
            $validated['notes'] ?? ''
        );

        return response()->json([
            'message' => 'Delivery confirmed. A Goods Receipt draft has been created for review.',
            'data'    => $note,
        ], 201);
    }

    // ── Items ────────────────────────────────────────────────────────────────

    /**
     * GET /vendor-portal/items
     * List the vendor's catalog items (active only by default).
     */
    public function items(Request $request): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');
        $vendor = Vendor::findOrFail($vendorId);

        $activeOnly = $request->query('active_only', 'true') !== 'false';
        $items = $this->itemService->list($vendor, $activeOnly);

        return response()->json(['data' => $items]);
    }

    /**
     * POST /vendor-portal/items
     * Create a new catalog item for the vendor's own catalog.
     */
    public function storeItem(Request $request): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');
        $vendor = Vendor::findOrFail($vendorId);

        $validated = $request->validate([
            'item_code'      => ['required', 'string', 'max:50'],
            'item_name'      => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'unit_of_measure' => ['required', 'string', 'max:20'],
            'unit_price'     => ['required', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
        ]);

        $item = $this->itemService->create($vendor, $validated, $request->user());

        return response()->json(['data' => $item], 201);
    }

    /**
     * PATCH /vendor-portal/items/{item}
     * Update a vendor catalog item.
     */
    public function updateItem(Request $request, int $item): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');
        $vendor = Vendor::findOrFail($vendorId);

        $vendorItem = $vendor->vendorItems()->findOrFail($item);

        $validated = $request->validate([
            'item_name'      => ['sometimes', 'string', 'max:200'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'unit_of_measure' => ['sometimes', 'string', 'max:20'],
            'unit_price'     => ['sometimes', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
        ]);

        $updated = $this->itemService->update($vendorItem, $validated, $request->user());

        return response()->json(['data' => $updated]);
    }

    /**
     * POST /vendor-portal/items/import
     * Bulk import items from CSV or Excel file.
     *
     * Expected columns: item_code, item_name, description, unit_of_measure, unit_price, is_active
     */
    public function importItems(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls,txt', 'max:5120'],
        ]);

        $vendorId = app('vendor_scope.vendor_id');
        $vendor = Vendor::findOrFail($vendorId);

        $import = new VendorItemImport();
        Excel::import($import, $request->file('file'));

        $result = $this->itemService->importRows($vendor, $import->getRows(), $request->user());

        return response()->json([
            'message' => "Import complete. {$result['created']} created, {$result['updated']} updated.",
            'data'    => $result,
        ]);
    }

    // ── Goods Receipts ───────────────────────────────────────────────────────

    /**
     * GET /vendor-portal/goods-receipts
     * List goods receipts for POs belonging to this vendor.
     */
    public function goodsReceipts(Request $request): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');

        $receipts = GoodsReceipt::with(['purchaseOrder'])
            ->whereHas('purchaseOrder', fn ($q) => $q->where('vendor_id', $vendorId))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($receipts);
    }

    // ── Invoices ─────────────────────────────────────────────────────────────

    /**
     * GET /vendor-portal/invoices
     * List AP invoices belonging to this vendor.
     */
    public function invoices(Request $request): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');

        $invoices = VendorInvoice::where('vendor_id', $vendorId)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($invoices);
    }

    /**
     * POST /vendor-portal/invoices
     * Submit a vendor invoice linked to a confirmed Goods Receipt.
     *
     * The vendor provides: invoice details (date, due date, net amount, VAT, OR number).
     * GL accounts are auto-resolved; internal staff will review and approve.
     */
    public function storeInvoice(Request $request): JsonResponse
    {
        $vendorId = app('vendor_scope.vendor_id');
        $vendor = Vendor::with('ewtRate')->findOrFail($vendorId);

        $validated = $request->validate([
            'goods_receipt_id' => ['required', 'integer'],
            'invoice_date'     => ['required', 'date'],
            'due_date'         => ['required', 'date', 'after_or_equal:invoice_date'],
            'net_amount'       => ['required', 'numeric', 'min:0.01'],
            'vat_amount'       => ['nullable', 'numeric', 'min:0'],
            'or_number'        => ['nullable', 'string', 'max:50'],
            'description'      => ['nullable', 'string', 'max:1000'],
        ]);

        // Ensure the GR belongs to a PO owned by this vendor
        $gr = GoodsReceipt::with('purchaseOrder')
            ->where('id', $validated['goods_receipt_id'])
            ->firstOrFail();

        if ((int) $gr->purchaseOrder->vendor_id !== $vendorId) {
            abort(403, 'This Goods Receipt does not belong to your vendor account.');
        }

        if ($gr->status !== 'confirmed') {
            abort(422, 'Invoice can only be submitted against a confirmed Goods Receipt.');
        }

        if ($gr->ap_invoice_created) {
            abort(422, 'An invoice has already been created for this Goods Receipt.');
        }

        // Auto-resolve GL accounts
        $apAccount = ChartOfAccount::where('code', '2001')->first();
        $expenseAccount = ChartOfAccount::where('code', '6001')->first();
        $fiscalPeriod = FiscalPeriod::open()->latest('date_from')->first();

        if ($fiscalPeriod === null) {
            abort(422, 'No open fiscal period — please contact your accounting department.');
        }

        $vatAmount = (float) ($validated['vat_amount'] ?? 0.00);
        $netAmount = (float) $validated['net_amount'];
        $invoiceDate = Carbon::parse($validated['invoice_date']);

        $ewtAmount = $vendor->is_ewt_subject
            ? $this->ewtService->computeForInvoice($vendor, $netAmount, $invoiceDate)
            : 0.00;

        $invoice = VendorInvoice::create([
            'vendor_id'          => $vendor->id,
            'fiscal_period_id'   => $fiscalPeriod->id,
            'ap_account_id'      => $apAccount?->id,
            'expense_account_id' => $expenseAccount?->id,
            'invoice_date'       => $validated['invoice_date'],
            'due_date'           => $validated['due_date'],
            'net_amount'         => $netAmount,
            'vat_amount'         => $vatAmount,
            'ewt_amount'         => $ewtAmount,
            'ewt_rate'           => $vendor->is_ewt_subject ? $vendor->ewtRate?->rate : null,
            'atc_code'           => $vendor->is_ewt_subject ? $vendor->atc_code : null,
            'or_number'          => $validated['or_number'] ?? null,
            'description'        => $validated['description'] ?? "Vendor-submitted invoice for GR {$gr->gr_reference}",
            'source'             => 'vendor_portal',
            'purchase_order_id'  => $gr->purchaseOrder->id,
            'status'             => 'draft',
            'created_by'         => $request->user()->id,
        ]);

        $gr->update(['ap_invoice_created' => true]);

        return response()->json([
            'message' => 'Invoice submitted successfully. It will be reviewed by accounting.',
            'data'    => $invoice,
        ], 201);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function assertVendorOwns(PurchaseOrder $po): void
    {
        $vendorId = app('vendor_scope.vendor_id');

        if ((int) $po->vendor_id !== $vendorId) {
            abort(403, 'Access denied — this Purchase Order does not belong to your vendor account.');
        }
    }
}
