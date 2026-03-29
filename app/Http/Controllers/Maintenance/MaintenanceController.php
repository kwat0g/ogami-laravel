<?php

declare(strict_types=1);

namespace App\Http\Controllers\Maintenance;

use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Maintenance\Services\MaintenanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\AddMaintenancePartRequest;
use App\Http\Requests\Maintenance\CompleteWorkOrderRequest;
use App\Http\Requests\Maintenance\StoreEquipmentRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceWorkOrderRequest;
use App\Http\Requests\Maintenance\StorePmScheduleRequest;
use App\Http\Resources\Maintenance\EquipmentResource;
use App\Http\Resources\Maintenance\MaintenanceWorkOrderPartResource;
use App\Http\Resources\Maintenance\MaintenanceWorkOrderResource;
use App\Http\Resources\Maintenance\PmScheduleResource;
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

    /** List archived equipment. */
    public function archivedEquipment(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Equipment::class);

        $equipmentService = app(\App\Domains\Maintenance\Services\EquipmentService::class);

        return EquipmentResource::collection(
            $equipmentService->listArchived(
                perPage: $request->integer('per_page', 20),
                search: $request->input('search'),
            )
        );
    }

    /** Restore an archived equipment record. */
    public function restoreEquipment(Request $request, int $equipment): EquipmentResource
    {
        $this->authorize('create', Equipment::class);

        $equipmentService = app(\App\Domains\Maintenance\Services\EquipmentService::class);
        $restored = $equipmentService->restore($equipment, $request->user());

        return new EquipmentResource($restored);
    }

    /** Permanently delete equipment — superadmin only. */
    public function forceDeleteEquipment(Request $request, int $equipment): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403, 'Only super admins can permanently delete records.');

        $equipmentService = app(\App\Domains\Maintenance\Services\EquipmentService::class);
        $equipmentService->forceDelete($equipment, $request->user());

        return response()->json(['message' => 'Equipment permanently deleted.']);
    }

    // ── Work Orders ──────────────────────────────────────────────────────────

    public function indexWorkOrders(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MaintenanceWorkOrder::class);

        return MaintenanceWorkOrderResource::collection(
            $this->service->paginateWorkOrders($request->only(['search', 'status', 'type', 'priority', 'equipment_id', 'per_page', 'with_archived']))
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

    public function completeWorkOrder(CompleteWorkOrderRequest $request, MaintenanceWorkOrder $maintenanceWorkOrder): MaintenanceWorkOrderResource
    {
        $this->authorize('update', $maintenanceWorkOrder);
        $data = $request->validated();

        return new MaintenanceWorkOrderResource(
            $this->service->completeWorkOrder($maintenanceWorkOrder, $data, $request->user())
        );
    }

    // ── Work Order Parts ────────────────────────────────────────────────────

    public function indexParts(MaintenanceWorkOrder $maintenanceWorkOrder): AnonymousResourceCollection
    {
        $this->authorize('view', $maintenanceWorkOrder);
        $parts = $maintenanceWorkOrder->spareParts()->with(['item', 'location'])->get();

        return MaintenanceWorkOrderPartResource::collection($parts);
    }

    public function addPart(AddMaintenancePartRequest $request, MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        $this->authorize('update', $maintenanceWorkOrder);

        $validated = $request->validated();

        $part = $this->service->addPart($maintenanceWorkOrder, $validated, $request->user()->id);

        return (new MaintenanceWorkOrderPartResource($part->load(['item', 'location'])))->response()->setStatusCode(201);
    }

    // ── PM Schedules ─────────────────────────────────────────────────────────

    public function storePmSchedule(StorePmScheduleRequest $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);
        $schedule = $this->service->storePmSchedule($equipment, $request->validated());

        return (new PmScheduleResource($schedule))->response()->setStatusCode(201);
    }
}
