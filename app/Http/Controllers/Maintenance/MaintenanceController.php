<?php

declare(strict_types=1);

namespace App\Http\Controllers\Maintenance;

use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Maintenance\Services\MaintenanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreEquipmentRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceWorkOrderRequest;
use App\Http\Requests\Maintenance\StorePmScheduleRequest;
use App\Http\Resources\Maintenance\EquipmentResource;
use App\Http\Resources\Maintenance\MaintenanceWorkOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $service) {}

    // ── Equipment ────────────────────────────────────────────────────────────

    public function indexEquipment(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Equipment::class);
        return EquipmentResource::collection(
            $this->service->paginateEquipment($request->only(['search', 'status', 'is_active', 'per_page', 'with_archived']))
        );
    }

    public function storeEquipment(StoreEquipmentRequest $request): JsonResponse
    {
        $this->authorize('create', Equipment::class);
        $eq = $this->service->storeEquipment($request->validated(), $request->user()->id);
        return (new EquipmentResource($eq))->response()->setStatusCode(201);
    }

    public function showEquipment(Equipment $equipment): EquipmentResource
    {
        $this->authorize('view', $equipment);
        return new EquipmentResource($equipment->load('workOrders', 'pmSchedules'));
    }

    public function updateEquipment(StoreEquipmentRequest $request, Equipment $equipment): EquipmentResource
    {
        $this->authorize('update', $equipment);
        return new EquipmentResource($this->service->updateEquipment($equipment, $request->validated()));
    }

    // ── Work Orders ──────────────────────────────────────────────────────────

    public function indexWorkOrders(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MaintenanceWorkOrder::class);
        return MaintenanceWorkOrderResource::collection(
            $this->service->paginateWorkOrders($request->only(['status', 'type', 'priority', 'equipment_id', 'per_page', 'with_archived']))
        );
    }

    public function storeWorkOrder(StoreMaintenanceWorkOrderRequest $request): JsonResponse
    {
        $this->authorize('create', MaintenanceWorkOrder::class);
        $mwo = $this->service->storeWorkOrder($request->validated(), $request->user()->id);
        return (new MaintenanceWorkOrderResource($mwo))->response()->setStatusCode(201);
    }

    public function showWorkOrder(MaintenanceWorkOrder $maintenanceWorkOrder): MaintenanceWorkOrderResource
    {
        $this->authorize('view', $maintenanceWorkOrder);
        return new MaintenanceWorkOrderResource($maintenanceWorkOrder->load('equipment', 'assignedTo'));
    }

    public function startWorkOrder(MaintenanceWorkOrder $maintenanceWorkOrder): MaintenanceWorkOrderResource
    {
        $this->authorize('update', $maintenanceWorkOrder);
        return new MaintenanceWorkOrderResource($this->service->startWorkOrder($maintenanceWorkOrder));
    }

    public function completeWorkOrder(Request $request, MaintenanceWorkOrder $maintenanceWorkOrder): MaintenanceWorkOrderResource
    {
        $this->authorize('update', $maintenanceWorkOrder);
        $data = $request->validate([
            'completion_notes'       => 'required|string',
            'labor_hours'            => 'nullable|numeric|min:0',
            'actual_completion_date' => 'nullable|date',
        ]);
        return new MaintenanceWorkOrderResource(
            $this->service->completeWorkOrder($maintenanceWorkOrder, $data)
        );
    }

    // ── PM Schedules ─────────────────────────────────────────────────────────

    public function storePmSchedule(StorePmScheduleRequest $request, Equipment $equipment): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $equipment);
        $schedule = $this->service->storePmSchedule($equipment, $request->validated());
        return response()->json(['data' => $schedule], 201);
    }
}
