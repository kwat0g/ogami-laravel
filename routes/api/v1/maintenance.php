<?php

declare(strict_types=1);

use App\Http\Controllers\Maintenance\MaintenanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::middleware(['auth:sanctum', 'module_access:maintenance'])->group(function (): void {
    // Equipment
    Route::get('/equipment', [MaintenanceController::class, 'indexEquipment']);
    Route::post('/equipment', [MaintenanceController::class, 'storeEquipment']);
    Route::get('/equipment/{equipment}', [MaintenanceController::class, 'showEquipment']);
    Route::put('/equipment/{equipment}', [MaintenanceController::class, 'updateEquipment']);
    Route::post('/equipment/{equipment}/pm-schedules', [MaintenanceController::class, 'storePmSchedule']);
    Route::get('/equipment-archived', [MaintenanceController::class, 'archivedEquipment']);
    Route::post('/equipment/{equipment}/restore', [MaintenanceController::class, 'restoreEquipment'])
        ->middleware('throttle:api-action');
    Route::delete('/equipment/{equipment}/force', [MaintenanceController::class, 'forceDeleteEquipment'])
        ->middleware('throttle:api-action');

    // Work Orders
    Route::get('/work-orders', [MaintenanceController::class, 'indexWorkOrders']);
    Route::post('/work-orders', [MaintenanceController::class, 'storeWorkOrder']);
    Route::get('/work-orders/{maintenanceWorkOrder}', [MaintenanceController::class, 'showWorkOrder']);
    Route::middleware('throttle:30,1')->group(function (): void {
        Route::patch('/work-orders/{maintenanceWorkOrder}/start', [MaintenanceController::class, 'startWorkOrder']);
        Route::patch('/work-orders/{maintenanceWorkOrder}/complete', [MaintenanceController::class, 'completeWorkOrder']);
    });

    // Work Order Parts (C1: Maintenance ↔ Inventory spare parts)
    Route::get('/work-orders/{maintenanceWorkOrder}/parts', [MaintenanceController::class, 'indexParts']);
    Route::post('/work-orders/{maintenanceWorkOrder}/parts', [MaintenanceController::class, 'addPart']);

    // ── Work Order Export (CSV) ──────────────────────────────────────────────
    Route::get('/work-orders/export', function (Request $request): StreamedResponse {
        abort_unless(auth()->user()?->hasPermissionTo('maintenance.view'), 403, 'Unauthorized');
        $query = DB::table('maintenance_work_orders')
            ->leftJoin('equipment', 'maintenance_work_orders.equipment_id', '=', 'equipment.id')
            ->select(
                'maintenance_work_orders.id',
                'maintenance_work_orders.title',
                'maintenance_work_orders.type',
                'maintenance_work_orders.priority',
                'maintenance_work_orders.status',
                'equipment.name as equipment_name',
                'equipment.asset_tag',
                'maintenance_work_orders.scheduled_date',
                'maintenance_work_orders.completed_at',
                'maintenance_work_orders.created_at',
            );

        if ($request->filled('status')) {
            $query->where('maintenance_work_orders.status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('maintenance_work_orders.type', $request->input('type'));
        }
        if ($request->filled('date_from')) {
            $query->where('maintenance_work_orders.scheduled_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('maintenance_work_orders.scheduled_date', '<=', $request->input('date_to'));
        }

        $rows = $query->orderBy('maintenance_work_orders.scheduled_date', 'desc')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['ID', 'Title', 'Type', 'Priority', 'Status', 'Equipment', 'Asset Tag', 'Scheduled', 'Completed', 'Created']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->id, $r->title, $r->type, $r->priority, $r->status, $r->equipment_name, $r->asset_tag, $r->scheduled_date, $r->completed_at, $r->created_at]);
            }
            fclose($out);
        }, 'work_orders_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    })->name('work-orders.export');

    // ── Maintenance Analytics (Phase 2) — MTBF/MTTR/OEE ──────────────────
    Route::get('/analytics/equipment/{equipment}', function (\Illuminate\Http\Request $request, \App\Domains\Maintenance\Models\Equipment $equipment): \Illuminate\Http\JsonResponse {
        $service = app(\App\Domains\Maintenance\Services\MaintenanceAnalyticsService::class);
        return response()->json(['data' => $service->equipmentMetrics(
            $equipment,
            $request->input('from_date'),
            $request->input('to_date'),
        )]);
    })->name('analytics.equipment');

    Route::get('/analytics/all', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
        $service = app(\App\Domains\Maintenance\Services\MaintenanceAnalyticsService::class);
        return response()->json(['data' => $service->allEquipmentMetrics(
            $request->input('from_date'),
            $request->input('to_date'),
        )]);
    })->name('analytics.all');

    Route::get('/analytics/cost-per-equipment', function (): \Illuminate\Http\JsonResponse {
        $service = app(\App\Domains\Maintenance\Services\MaintenanceAnalyticsService::class);
        return response()->json(['data' => $service->costPerEquipment()]);
    })->name('analytics.cost');
});
