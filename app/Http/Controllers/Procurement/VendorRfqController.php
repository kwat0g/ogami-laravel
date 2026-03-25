<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Domains\AP\Models\Vendor;
use App\Domains\Procurement\Models\VendorRfq;
use App\Domains\Procurement\Services\VendorRfqService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VendorRfqController extends Controller
{
    public function __construct(private readonly VendorRfqService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VendorRfq::class);

        $rfqs = VendorRfq::with(['purchaseRequest', 'createdBy'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->input('status')),
            )
            ->when(
                $request->filled('purchase_request_id'),
                fn ($q) => $q->where('purchase_request_id', $request->integer('purchase_request_id')),
            )
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($rfqs);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', VendorRfq::class);

        $data = $request->validate([
            'scope_description' => ['required', 'string', 'min:10'],
            'deadline_date' => ['nullable', 'date', 'after:today'],
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $rfq = $this->service->create($data, $request->user());

        return response()->json(['data' => $rfq->load('createdBy')], 201);
    }

    public function show(VendorRfq $vendorRfq): JsonResponse
    {
        $this->authorize('view', $vendorRfq);

        return response()->json([
            'data' => $vendorRfq->load(['purchaseRequest', 'createdBy', 'vendorInvitations.vendor']),
        ]);
    }

    /**
     * Send the RFQ to one or more vendors (draft → sent).
     */
    public function send(Request $request, VendorRfq $vendorRfq): JsonResponse
    {
        $this->authorize('update', $vendorRfq);

        $data = $request->validate([
            'vendor_ids' => ['required', 'array', 'min:1'],
            'vendor_ids.*' => ['integer', 'exists:vendors,id'],
        ]);

        $rfq = $this->service->send($vendorRfq, $data['vendor_ids'], $request->user());

        return response()->json(['data' => $rfq->load('vendorInvitations.vendor')]);
    }

    /**
     * Record a vendor's quote response.
     */
    public function receiveQuote(Request $request, VendorRfq $vendorRfq, Vendor $vendor): JsonResponse
    {
        $this->authorize('update', $vendorRfq);

        $data = $request->validate([
            'quoted_amount_centavos' => ['required', 'integer', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:1'],
            'vendor_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $rfq = $this->service->receiveQuote($vendorRfq, $vendor, $data);

        return response()->json(['data' => $rfq->load('vendorInvitations.vendor')]);
    }

    /**
     * Record that a vendor declined to quote.
     */
    public function recordDecline(Request $request, VendorRfq $vendorRfq, Vendor $vendor): JsonResponse
    {
        $this->authorize('update', $vendorRfq);

        $data = $request->validate([
            'vendor_remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->recordDecline($vendorRfq, $vendor, $data['vendor_remarks'] ?? null);

        return response()->json(['message' => 'Vendor decline recorded.']);
    }

    public function close(VendorRfq $vendorRfq): JsonResponse
    {
        $this->authorize('update', $vendorRfq);

        $rfq = $this->service->close($vendorRfq);

        return response()->json(['data' => $rfq]);
    }

    public function cancel(VendorRfq $vendorRfq): JsonResponse
    {
        $this->authorize('delete', $vendorRfq);

        $rfq = $this->service->cancel($vendorRfq);

        return response()->json(['data' => $rfq]);
    }

    public function award(VendorRfq $vendorRfq, Vendor $vendor): PurchaseOrderResource
    {
        $this->authorize('update', $vendorRfq);

        $po = $this->service->award($vendorRfq, $vendor, auth()->user());

        return new PurchaseOrderResource($po->load(['vendor', 'items']));
    }
}
