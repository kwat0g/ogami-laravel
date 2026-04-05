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

        return new DeliveryScheduleResource($deliverySchedule->load(
            'customer',
            'clientOrder',
            'productItem',
            'items.productItem',
            'items.productionOrders',
            'legacyProductionOrders',
            'deliveryReceipts'
        ));
    }

    public function update(StoreDeliveryScheduleRequest $request, DeliverySchedule $deliverySchedule): DeliveryScheduleResource
    {
        $this->authorize('update', $deliverySchedule);

        return new DeliveryScheduleResource($this->service->update($deliverySchedule, $request->validated()));
    }

    /**
     * Fulfill delivery schedule directly from stock (no Production Order needed)
     */
    public function fulfillFromStock(Request $request, DeliverySchedule $deliverySchedule): DeliveryScheduleResource
    {
        $this->authorize('update', $deliverySchedule);

        $userId = $request->user()?->id ?? 1;

        return new DeliveryScheduleResource($this->service->fulfillFromStock($deliverySchedule, $userId));
    }

    // ── Workflow Actions (consolidated from CDS) ────────────────────────────

    public function dispatch(Request $request, DeliverySchedule $deliverySchedule): DeliveryScheduleResource
    {
        $this->authorize('update', $deliverySchedule);

        $validated = $request->validate([
            'delivery_notes' => 'nullable|string|max:500',
        ]);

        $userId = $request->user()?->id ?? 1;

        return new DeliveryScheduleResource(
            $this->service->dispatchSchedule($deliverySchedule, $userId, $validated['delivery_notes'] ?? null)
        );
    }

    public function markDelivered(Request $request, DeliverySchedule $deliverySchedule): DeliveryScheduleResource
    {
        $this->authorize('update', $deliverySchedule);

        $validated = $request->validate([
            'delivery_date' => 'required|date',
        ]);

        $userId = $request->user()?->id ?? 1;

        return new DeliveryScheduleResource(
            $this->service->markScheduleDelivered($deliverySchedule, $validated['delivery_date'], $userId)
        );
    }

    public function acknowledgeReceipt(Request $request, DeliverySchedule $deliverySchedule): DeliveryScheduleResource
    {
        $this->authorize('respond', $deliverySchedule);

        $validated = $request->validate([
            'item_acknowledgments' => 'required|array',
            'item_acknowledgments.*.item_id' => 'required|integer',
            'item_acknowledgments.*.received_qty' => 'required|numeric|min:0',
            'item_acknowledgments.*.condition' => 'required|string|in:good,damaged,missing',
            'item_acknowledgments.*.notes' => 'nullable|string|max:500',
            'general_notes' => 'nullable|string|max:1000',
        ]);

        $userId = $request->user()?->id ?? 1;

        return new DeliveryScheduleResource(
            $this->service->acknowledgeReceipt(
                $deliverySchedule,
                $validated['item_acknowledgments'],
                $validated['general_notes'] ?? null,
                $userId
            )
        );
    }

    public function notifyMissingItems(Request $request, DeliverySchedule $deliverySchedule): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $deliverySchedule);

        $validated = $request->validate([
            'missing_items' => 'required|array|min:1',
            'missing_items.*.item_id' => 'required|integer',
            'missing_items.*.reason' => 'required|string|max:255',
            'expected_delivery_date' => 'nullable|date',
            'message' => 'nullable|string|max:1000',
        ]);

        $userId = $request->user()?->id ?? 1;

        $this->service->notifyMissingItems(
            $deliverySchedule,
            $validated['missing_items'],
            $validated['expected_delivery_date'] ?? null,
            $validated['message'] ?? null,
            $userId
        );

        return response()->json(['message' => 'Customer notified about missing items.']);
    }
}
