<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Domains\Production\Models\CombinedDeliverySchedule;
use App\Domains\Production\Services\CombinedDeliveryScheduleService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Production\CombinedDeliveryScheduleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CombinedDeliveryScheduleController extends Controller
{
    public function __construct(
        private readonly CombinedDeliveryScheduleService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CombinedDeliverySchedule::class);

        return CombinedDeliveryScheduleResource::collection(
            $this->service->paginate($request->only([
                'customer_id', 'status', 'date_from', 'date_to', 'per_page',
            ]))
        );
    }

    public function show(CombinedDeliverySchedule $schedule): CombinedDeliveryScheduleResource
    {
        $this->authorize('view', $schedule);

        return new CombinedDeliveryScheduleResource($schedule->load([
            'clientOrder.items',
            'clientOrder.customer',
            'itemSchedules.productItem',
            'itemSchedules.productionOrders',
            'customer',
        ]));
    }

    public function dispatch(Request $request, CombinedDeliverySchedule $schedule): CombinedDeliveryScheduleResource
    {
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'vehicle_id' => 'nullable|integer',
            'driver_name' => 'nullable|string|max:100',
            'delivery_notes' => 'nullable|string|max:1000',
        ]);

        $userId = $request->user()?->id ?? 1;
        $schedule = $this->service->dispatch(
            $schedule,
            $userId,
            $validated['vehicle_id'] ?? null,
            $validated['driver_name'] ?? null,
            $validated['delivery_notes'] ?? null
        );

        return new CombinedDeliveryScheduleResource($schedule->fresh());
    }

    public function markDelivered(Request $request, CombinedDeliverySchedule $schedule): CombinedDeliveryScheduleResource
    {
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'delivery_date' => 'required|date',
            'received_by' => 'nullable|string|max:100',
            'delivery_receipt_number' => 'nullable|string|max:50',
        ]);

        $userId = $request->user()?->id ?? 1;
        $schedule = $this->service->markDelivered(
            $schedule,
            $validated['delivery_date'],
            $userId,
            $validated['received_by'] ?? null,
            $validated['delivery_receipt_number'] ?? null
        );

        return new CombinedDeliveryScheduleResource($schedule->fresh());
    }

    public function notifyMissingItems(Request $request, CombinedDeliverySchedule $schedule): JsonResponse
    {
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'missing_items' => 'required|array',
            'missing_items.*.item_id' => 'required|integer',
            'missing_items.*.reason' => 'required|string|max:500',
            'expected_delivery_date' => 'nullable|date|after:today',
            'message' => 'nullable|string|max:2000',
        ]);

        $userId = $request->user()?->id ?? 1;
        $this->service->notifyMissingItems(
            $schedule,
            $validated['missing_items'],
            $validated['expected_delivery_date'] ?? null,
            $validated['message'] ?? null,
            $userId
        );

        return response()->json(['message' => 'Notification sent to client']);
    }

    /**
     * Client acknowledges receipt of delivery
     */
    public function acknowledgeReceipt(Request $request, CombinedDeliverySchedule $schedule): CombinedDeliveryScheduleResource
    {
        $this->authorize('respond', $schedule);

        $validated = $request->validate([
            'item_acknowledgments' => 'required|array',
            'item_acknowledgments.*.item_id' => 'required|integer',
            'item_acknowledgments.*.received_qty' => 'required|numeric|min:0',
            'item_acknowledgments.*.condition' => 'required|string|in:good,damaged,missing',
            'item_acknowledgments.*.notes' => 'nullable|string|max:500',
            'general_notes' => 'nullable|string|max:2000',
        ]);

        $userId = $request->user()?->id ?? 1;
        $schedule = $this->service->acknowledgeReceipt(
            $schedule,
            $validated['item_acknowledgments'],
            $validated['general_notes'] ?? null,
            $userId
        );

        return new CombinedDeliveryScheduleResource($schedule->fresh());
    }
}
