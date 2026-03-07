<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Production\Services\DeliveryScheduleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StoreDeliveryScheduleRequest;
use App\Http\Resources\Production\DeliveryScheduleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DeliveryScheduleController extends Controller
{
    public function __construct(private readonly DeliveryScheduleService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeliverySchedule::class);

        return DeliveryScheduleResource::collection(
            $this->service->paginate($request->only([
                'customer_id', 'status', 'type', 'date_from', 'date_to', 'per_page', 'with_archived',
            ]))
        );
    }

    public function store(StoreDeliveryScheduleRequest $request): DeliveryScheduleResource
    {
        $this->authorize('create', DeliverySchedule::class);

        return new DeliveryScheduleResource($this->service->store($request->validated()));
    }

    public function show(DeliverySchedule $deliverySchedule): DeliveryScheduleResource
    {
        $this->authorize('view', $deliverySchedule);

        return new DeliveryScheduleResource($deliverySchedule->load('customer', 'productItem', 'productionOrders'));
    }

    public function update(StoreDeliveryScheduleRequest $request, DeliverySchedule $deliverySchedule): DeliveryScheduleResource
    {
        $this->authorize('update', $deliverySchedule);

        return new DeliveryScheduleResource($this->service->update($deliverySchedule, $request->validated()));
    }
}
