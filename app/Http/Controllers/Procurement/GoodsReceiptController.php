<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Services\GoodsReceiptService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreGoodsReceiptRequest;
use App\Http\Resources\Procurement\GoodsReceiptResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $service,
    ) {}

    /**
     * List GRs.
     *   ?status=draft|confirmed
     *   ?purchase_order_id=12
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        try {
            $query = GoodsReceipt::with(['purchaseOrder', 'receivedBy'])
                ->when($request->boolean('with_archived'), fn ($q) => $q->withTrashed())
                ->when(
                    $request->filled('status'),
                    fn ($q) => $q->where('status', $request->input('status')),
                )
                ->when(
                    $request->filled('purchase_order_id'),
                    fn ($q) => $q->where('purchase_order_id', $request->integer('purchase_order_id')),
                )
                ->orderByDesc('created_at');

            return GoodsReceiptResource::collection($query->paginate(25));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[Procurement] GR index failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error_code' => 'GR_INDEX_ERROR',
                'message' => 'Failed to load goods receipts. Please check that all migrations have been run.',
            ], 500);
        }
    }

    public function store(StoreGoodsReceiptRequest $request): GoodsReceiptResource
    {
        $this->authorize('create', GoodsReceipt::class);

        $validated = $request->validated();
        $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);

        $gr = $this->service->store(
            po: $po,
            data: $validated,
            items: $validated['items'],
            actor: auth()->user(),
        );

        return new GoodsReceiptResource($gr->load(['purchaseOrder', 'receivedBy', 'items']));
    }

    /**
     * Update a draft GR header (received_date, delivery_note_number, condition_notes).
     */
    public function update(Request $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('confirm', $goodsReceipt);

        $data = $request->validate([
            'received_date' => ['sometimes', 'date'],
            'delivery_note_number' => ['nullable', 'string', 'max:255'],
            'condition_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $gr = $this->service->update($goodsReceipt, $data);

        return new GoodsReceiptResource($gr->load(['purchaseOrder', 'receivedBy', 'items']));
    }

    /**
     * Update a specific GR line item (quantity_received, condition, remarks).
     */
    public function updateItem(Request $request, GoodsReceipt $goodsReceipt, int $itemId): GoodsReceiptResource
    {
        $this->authorize('confirm', $goodsReceipt);

        $data = $request->validate([
            'quantity_received' => ['sometimes', 'numeric', 'min:0'],
            'condition' => ['sometimes', 'string', 'in:good,damaged,partial,rejected'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $gr = $this->service->updateItem($goodsReceipt->load('items'), $itemId, $data);

        return new GoodsReceiptResource($gr->load(['purchaseOrder', 'receivedBy', 'items.poItem']));
    }

    public function show(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('view', $goodsReceipt);

        return new GoodsReceiptResource(
            $goodsReceipt->load(['purchaseOrder.vendor', 'receivedBy', 'confirmedBy', 'items.poItem'])
        );
    }

    /**
     * Submit a GR for incoming quality control inspection.
     * Flow: draft -> pending_qc -> confirmed (after QC passes)
     */
    public function submitForQc(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('confirm', $goodsReceipt);

        $gr = $this->service->submitForQc($goodsReceipt->load('items'), auth()->user());

        return new GoodsReceiptResource($gr->load(['purchaseOrder', 'receivedBy', 'items']));
    }

    public function confirm(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('confirm', $goodsReceipt);

        $gr = $this->service->confirm($goodsReceipt->load('items'), auth()->user());

        return new GoodsReceiptResource($gr->load(['purchaseOrder', 'confirmedBy', 'items']));
    }

    public function reject(Request $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('confirm', $goodsReceipt);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $gr = $this->service->reject($goodsReceipt, auth()->user(), $validated['reason']);

        return new GoodsReceiptResource($gr->load(['purchaseOrder', 'items']));
    }

    public function destroy(GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->authorize('delete', $goodsReceipt);

        if ($goodsReceipt->status !== 'draft') {
            return response()->json([
                'success' => false,
                'error_code' => 'GR_NOT_DRAFT',
                'message' => 'Only draft Goods Receipts can be cancelled.',
            ], 422);
        }

        $goodsReceipt->items()->delete();
        $goodsReceipt->delete();

        return response()->json(['success' => true, 'message' => 'Goods Receipt cancelled.']);
    }
}
