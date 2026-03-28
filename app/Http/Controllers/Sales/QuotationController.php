<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Domains\Sales\Models\Quotation;
use App\Domains\Sales\Services\QuotationService;
use App\Domains\Sales\Services\SalesOrderService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreQuotationRequest;
use App\Http\Resources\Sales\QuotationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

final class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly SalesOrderService $salesOrderService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Quotation::class);

        $page = $this->quotationService->paginate($request->only(['search', 'status', 'customer_id', 'per_page']));

        return QuotationResource::collection($page);
    }

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        // Authorization handled by StoreQuotationRequest::authorize()
        $quotation = $this->quotationService->store($request->validated(), $request->user());

        return (new QuotationResource($quotation))->response()->setStatusCode(201);
    }

    public function show(Quotation $quotation): QuotationResource
    {
        $this->authorize('view', $quotation);

        return new QuotationResource(
            $quotation->load(['customer', 'contact', 'opportunity', 'items.item', 'createdBy'])
        );
    }

    public function send(Quotation $quotation): QuotationResource
    {
        $this->authorize('send', $quotation);

        return new QuotationResource($this->quotationService->send($quotation));
    }

    public function accept(Quotation $quotation): QuotationResource
    {
        $this->authorize('accept', $quotation);

        return new QuotationResource($this->quotationService->accept($quotation));
    }

    public function reject(Quotation $quotation): QuotationResource
    {
        $this->authorize('reject', $quotation);

        return new QuotationResource($this->quotationService->reject($quotation));
    }

    public function convertToOrder(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorize('convertToOrder', $quotation);

        $order = $this->salesOrderService->createFromQuotation($quotation, $request->user());

        return response()->json(['data' => $order], 201);
    }
}
