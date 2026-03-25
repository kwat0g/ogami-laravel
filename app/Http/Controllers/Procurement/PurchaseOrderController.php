<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Services\PurchaseOrderService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Requests\Procurement\UpdatePurchaseOrderRequest;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {}

    /**
     * List POs.
     *   ?status=draft|sent|partially_received|fully_received|closed|cancelled
     *   ?vendor_id=5
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $query = PurchaseOrder::with(['vendor', 'purchaseRequest', 'createdBy'])
            ->when($request->boolean('with_archived'), fn ($q) => $q->withTrashed())
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->input('status')),
            )
            ->when(
                $request->filled('vendor_id'),
                fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')),
            )
            ->orderByDesc('created_at');

        return PurchaseOrderResource::collection($query->paginate(25));
    }

    public function store(StorePurchaseOrderRequest $request): PurchaseOrderResource
    {
        // Enforce strict flow: POs must be auto-created from Approved PRs.
        // Manual creation is disabled.
        abort(403, 'Manual Purchase Order creation is disabled. POs are automatically created upon PR approval.');
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('view', $purchaseOrder);

        return new PurchaseOrderResource(
            $purchaseOrder->load(['vendor', 'purchaseRequest', 'createdBy', 'items', 'goodsReceipts', 'fulfillmentNotes'])
        );
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('update', $purchaseOrder);

        $validated = $request->validated();
        $po = $this->service->update(
            po: $purchaseOrder,
            data: $validated,
            items: $validated['items'] ?? [],
        );

        return new PurchaseOrderResource($po->load(['vendor', 'items']));
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('send', $purchaseOrder);

        $validated = $request->validate([
            'delivery_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $po = $this->service->send($purchaseOrder, $validated['delivery_date'] ?? null);

        return new PurchaseOrderResource($po->load(['vendor', 'items']));
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $request->validate(['reason' => ['required', 'string', 'min:5']]);

        $this->service->cancel($purchaseOrder, $request->string('reason')->value());

        return response()->json(['success' => true, 'message' => 'Purchase Order cancelled.']);
    }

    public function acceptChanges(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('manage', $purchaseOrder);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->service->officerAcceptChanges(
            $purchaseOrder,
            auth()->user(),
            $validated['remarks'] ?? '',
        );

        /** @var PurchaseOrder $po */
        $po = $result['po'];
        $unmetItems = $result['unmet_items'];

        $response = ['data' => new PurchaseOrderResource($po->load(['vendor', 'purchaseRequest', 'items', 'fulfillmentNotes']))];

        if ($po->requires_budget_recheck) {
            $response['requires_budget_recheck'] = true;
        }

        if (! empty($unmetItems)) {
            $response['warnings'] = [[
                'type' => 'UNMET_QUANTITY',
                'message' => 'Vendor reduced quantity for '.count($unmetItems).' item(s). Consider submitting a new Purchase Request for the remaining quantities.',
                'items' => $unmetItems,
            ]];
        }

        return response()->json($response);
    }

    public function rejectChanges(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('manage', $purchaseOrder);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $po = $this->service->officerRejectChanges(
            $purchaseOrder,
            auth()->user(),
            $validated['remarks'],
        );

        return new PurchaseOrderResource($po->load(['vendor', 'purchaseRequest', 'items', 'fulfillmentNotes']));
    }

    /** Export PO as PDF. */
    public function pdf(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        $po = $purchaseOrder->load([
            'vendor', 'purchaseRequest', 'createdBy', 'items',
        ]);

        $settings = [
            'company_name' => config('app.company_name', 'Ogami Manufacturing Corp.'),
            'company_address' => config('app.company_address', ''),
        ];

        $pdf = Pdf::loadView('procurement.purchase-order-pdf', compact('po', 'settings'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("PO-{$po->po_reference}.pdf");
    }

    /**
     * Assign vendor + map line items for an auto-created PO draft (Phase 4).
     * Only valid when vendor_id is null (auto-created from VP-approved PR).
     */
    public function assignVendor(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('manage', $purchaseOrder);

        $validated = $request->validate([
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'delivery_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'delivery_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.po_item_id' => ['required', 'integer'],
            'items.*.item_master_id' => ['nullable', 'integer', 'exists:item_masters,id'],
            'items.*.agreed_unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $po = $this->service->finalizeVendorAssignment(
            $purchaseOrder,
            $validated,
            $validated['items'],
            auth()->user(),
        );

        return new PurchaseOrderResource($po->load(['vendor', 'purchaseRequest', 'items']));
    }
}
