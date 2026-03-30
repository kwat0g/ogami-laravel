<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\ProductionOrderService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\LogProductionOutputRequest;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Http\Resources\Production\ProductionOrderResource;
use App\Http\Resources\Production\ProductionOutputLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductionOrderController extends Controller
{
    public function __construct(private readonly ProductionOrderService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductionOrder::class);

        return ProductionOrderResource::collection(
            $this->service->paginate($request->only(['search', 'status', 'product_item_id', 'per_page', 'with_archived']))
        );
    }

    public function store(StoreProductionOrderRequest $request): ProductionOrderResource
    {
        $this->authorize('create', ProductionOrder::class);

        return new ProductionOrderResource(
            $this->service->store($request->validated(), $request->user())
        );
    }

    public function update(Request $request, ProductionOrder $productionOrder): ProductionOrderResource
    {
        $this->authorize('update', $productionOrder);

        $validated = $request->validate([
            'product_item_id' => ['sometimes', 'integer', 'exists:item_masters,id'],
            'bom_id' => ['sometimes', 'integer', 'exists:bill_of_materials,id'],
            'qty_required' => ['sometimes', 'numeric', 'min:0.0001'],
            'target_start_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'target_end_date' => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:target_start_date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return new ProductionOrderResource($this->service->update($productionOrder, $validated));
    }

    public function close(ProductionOrder $productionOrder): ProductionOrderResource
    {
        $this->authorize('complete', $productionOrder);

        return new ProductionOrderResource($this->service->close($productionOrder));
    }

    public function show(ProductionOrder $productionOrder): ProductionOrderResource
    {
        $this->authorize('view', $productionOrder);

        $productionOrder->loadCount(['materialRequisitions as pending_mrq_count' => fn ($q) => $q->whereNotIn('status', ['fulfilled', 'cancelled', 'rejected'])]);

        return new ProductionOrderResource(
            $productionOrder->load('productItem', 'bom.components.componentItem', 'createdBy', 'outputLogs.operator', 'inspections')
        );
    }

    public function release(Request $request, ProductionOrder $productionOrder): ProductionOrderResource
    {
        $this->authorize('release', $productionOrder);

        $options = [];
        if ($request->boolean('force_release')) {
            // PROD-002: Only users with production.qc-override can force-release
            if (! $request->user()?->can('production.qc-override')) {
                abort(403, 'You do not have permission to override QC blocks.');
            }
            $options['force_release'] = true;
        }

        return new ProductionOrderResource($this->service->release($productionOrder, $options));
    }

    public function stockCheck(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('view', $productionOrder);

        return response()->json([
            'data' => $this->service->stockCheck($productionOrder),
        ]);
    }

    public function start(ProductionOrder $productionOrder): ProductionOrderResource
    {
        $this->authorize('start', $productionOrder);

        return new ProductionOrderResource($this->service->start($productionOrder));
    }

    public function complete(ProductionOrder $productionOrder): ProductionOrderResource
    {
        $this->authorize('complete', $productionOrder);

        return new ProductionOrderResource($this->service->complete($productionOrder));
    }

    public function cancel(ProductionOrder $productionOrder): ProductionOrderResource
    {
        $this->authorize('cancel', $productionOrder);

        return new ProductionOrderResource($this->service->cancel($productionOrder));
    }

    public function void(ProductionOrder $productionOrder): ProductionOrderResource
    {
        $this->authorize('cancel', $productionOrder);

        return new ProductionOrderResource($this->service->void($productionOrder));
    }

    public function logOutput(LogProductionOutputRequest $request, ProductionOrder $productionOrder): ProductionOutputLogResource
    {
        $this->authorize('logOutput', $productionOrder);

        return new ProductionOutputLogResource(
            $this->service->logOutput($productionOrder, $request->validated(), $request->user())
        );
    }

    /**
     * Get smart defaults for production order creation.
     * Suggests BOM and calculates end date based on product item.
     */
    public function smartDefaults(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductionOrder::class);

        $validated = $request->validate([
            'product_item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'target_start_date' => ['nullable', 'date', 'date_format:Y-m-d'],
        ]);

        $defaults = $this->service->getSmartDefaults(
            $validated['product_item_id'],
            $validated['target_start_date'] ?? null
        );

        return response()->json([
            'data' => $defaults,
        ]);
    }

    /** List archived (soft-deleted) production orders. */
    public function archived(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductionOrder::class);

        return ProductionOrderResource::collection(
            $this->service->listArchived($request->only(['search', 'per_page']))
        );
    }

    /** Restore a soft-deleted production order from the archive. */
    public function restore(Request $request, int $productionOrder): ProductionOrderResource
    {
        $this->authorize('create', ProductionOrder::class);

        $order = $this->service->restoreOrder($productionOrder, $request->user());

        return new ProductionOrderResource($order->load('productItem', 'bom'));
    }

    /** Permanently delete a production order — superadmin only. */
    public function forceDelete(Request $request, int $productionOrder): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403, 'Only super admins can permanently delete records.');

        $this->service->forceDeleteOrder($productionOrder, $request->user());

        return response()->json(['message' => 'Production order permanently deleted.']);
    }
}
