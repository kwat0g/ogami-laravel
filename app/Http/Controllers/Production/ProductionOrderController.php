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
            $this->service->paginate($request->only(['status', 'product_item_id', 'per_page', 'with_archived']))
        );
    }

    public function store(StoreProductionOrderRequest $request): ProductionOrderResource
    {
        $this->authorize('create', ProductionOrder::class);

        return new ProductionOrderResource(
            $this->service->store($request->validated(), $request->user())
        );
    }

    public function show(ProductionOrder $productionOrder): ProductionOrderResource
    {
        $this->authorize('view', $productionOrder);

        return new ProductionOrderResource(
            $productionOrder->load('productItem', 'bom.components.componentItem', 'createdBy', 'outputLogs.operator')
        );
    }

    public function release(ProductionOrder $productionOrder): ProductionOrderResource
    {
        $this->authorize('release', $productionOrder);

        return new ProductionOrderResource($this->service->release($productionOrder));
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

    public function logOutput(LogProductionOutputRequest $request, ProductionOrder $productionOrder): ProductionOutputLogResource
    {
        $this->authorize('logOutput', $productionOrder);

        return new ProductionOutputLogResource(
            $this->service->logOutput($productionOrder, $request->validated(), $request->user())
        );
    }
}
