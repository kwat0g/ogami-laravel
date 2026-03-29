<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Services\SalesOrderService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Resources\Sales\SalesOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesOrder::class);

        $page = $this->service->paginate($request->only(['search', 'status', 'customer_id', 'per_page']));

        return SalesOrderResource::collection($page);
    }

    public function store(StoreSalesOrderRequest $request): JsonResponse
    {
        // Authorization handled by StoreSalesOrderRequest::authorize()
        $order = $this->service->store($request->validated(), $request->user());

        return (new SalesOrderResource($order))->response()->setStatusCode(201);
    }

    public function show(SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('view', $salesOrder);

        return new SalesOrderResource(
            $salesOrder->load([
                'customer', 'contact', 'quotation', 'opportunity', 'items.item', 'createdBy',
            ])
        );
    }

    public function confirm(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('confirm', $salesOrder);

        return new SalesOrderResource(
            $this->service->confirm($salesOrder, $request->user())
        );
    }

    public function cancel(SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('cancel', $salesOrder);

        return new SalesOrderResource($this->service->cancel($salesOrder));
    }

    /** Soft-delete (archive) a sales order. */
    public function destroy(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('delete', $salesOrder);

        $this->service->archive($salesOrder, $request->user());

        return response()->json(['message' => 'Sales order archived.']);
    }

    /** List archived sales orders. */
    public function archived(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesOrder::class);

        return SalesOrderResource::collection(
            $this->service->listArchived(
                perPage: $request->integer('per_page', 20),
                search: $request->input('search'),
            )
        );
    }

    /** Restore a soft-deleted sales order. */
    public function restore(Request $request, int $salesOrder): SalesOrderResource
    {
        $this->authorize('create', SalesOrder::class);

        $order = $this->service->restore($salesOrder, $request->user());

        return new SalesOrderResource($order);
    }

    /** Permanently delete — superadmin only. */
    public function forceDelete(Request $request, int $salesOrder): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403, 'Only super admins can permanently delete records.');

        $this->service->forceDelete($salesOrder, $request->user());

        return response()->json(['message' => 'Sales order permanently deleted.']);
    }
}
