<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Domains\Sales\Models\Quotation;
use App\Domains\Sales\Services\QuotationService;
use App\Domains\Sales\Services\SalesOrderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly SalesOrderService $salesOrderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->quotationService->paginate($request->only(['search', 'status', 'customer_id', 'per_page']));

        return response()->json($page);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contact_id' => ['sometimes', 'integer', 'exists:crm_contacts,id'],
            'opportunity_id' => ['sometimes', 'integer', 'exists:crm_opportunities,id'],
            'validity_date' => ['required', 'date', 'after:today'],
            'notes' => ['sometimes', 'string'],
            'terms_and_conditions' => ['sometimes', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price_centavos' => ['required', 'integer', 'min:0'],
            'items.*.remarks' => ['sometimes', 'string'],
        ]);

        $quotation = $this->quotationService->store($data, $request->user());

        return response()->json(['data' => $quotation], 201);
    }

    public function show(Quotation $quotation): JsonResponse
    {
        return response()->json([
            'data' => $quotation->load(['customer', 'contact', 'opportunity', 'items.item', 'createdBy']),
        ]);
    }

    public function send(Quotation $quotation): JsonResponse
    {
        return response()->json(['data' => $this->quotationService->send($quotation)]);
    }

    public function accept(Quotation $quotation): JsonResponse
    {
        return response()->json(['data' => $this->quotationService->accept($quotation)]);
    }

    public function reject(Quotation $quotation): JsonResponse
    {
        return response()->json(['data' => $this->quotationService->reject($quotation)]);
    }

    public function convertToOrder(Request $request, Quotation $quotation): JsonResponse
    {
        $order = $this->salesOrderService->createFromQuotation($quotation, $request->user());

        return response()->json(['data' => $order], 201);
    }
}
