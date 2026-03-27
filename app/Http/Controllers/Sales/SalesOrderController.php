<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Services\SalesOrderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->service->paginate($request->only(['status', 'customer_id', 'per_page']));

        return response()->json($page);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contact_id' => ['sometimes', 'integer', 'exists:crm_contacts,id'],
            'quotation_id' => ['sometimes', 'integer', 'exists:quotations,id'],
            'opportunity_id' => ['sometimes', 'integer', 'exists:crm_opportunities,id'],
            'requested_delivery_date' => ['sometimes', 'date'],
            'promised_delivery_date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price_centavos' => ['required', 'integer', 'min:0'],
            'items.*.remarks' => ['sometimes', 'string'],
        ]);

        $order = $this->service->store($data, $request->user());

        return response()->json(['data' => $order], 201);
    }

    public function show(SalesOrder $salesOrder): JsonResponse
    {
        return response()->json([
            'data' => $salesOrder->load([
                'customer', 'contact', 'quotation', 'opportunity', 'items.item', 'createdBy',
            ]),
        ]);
    }

    public function confirm(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        return response()->json([
            'data' => $this->service->confirm($salesOrder, $request->user()),
        ]);
    }

    public function cancel(SalesOrder $salesOrder): JsonResponse
    {
        return response()->json(['data' => $this->service->cancel($salesOrder)]);
    }
}
