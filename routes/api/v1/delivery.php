<?php

declare(strict_types=1);

use App\Http\Controllers\Delivery\DeliveryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::middleware(['auth:sanctum', 'module_access:delivery'])->group(function (): void {
    // Delivery Receipts
    Route::get('/receipts', [DeliveryController::class, 'indexReceipts']);
    Route::post('/receipts', [DeliveryController::class, 'storeReceipt']);
    Route::get('/receipts/{deliveryReceipt}', [DeliveryController::class, 'showReceipt']);
    Route::patch('/receipts/{deliveryReceipt}/confirm', [DeliveryController::class, 'confirmReceipt'])
        ->middleware('throttle:30,1');
    // ── Fleet / Vehicles CRUD ──────────────────────────────────────────────
    Route::get('/vehicles', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
        abort_unless($request->user()?->hasPermissionTo('delivery.view'), 403, 'Unauthorized');

        $activeDeliveryStatuses = ['confirmed', 'dispatched', 'in_transit', 'partially_delivered'];

        $vehicles = \App\Domains\Delivery\Models\Vehicle::query()
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->withCount([
                'deliveryReceipts as active_deliveries_count' => fn ($q) => $q->whereIn('status', $activeDeliveryStatuses),
                'deliveryReceipts as completed_deliveries_count' => fn ($q) => $q->where('status', 'delivered'),
                'deliveryReceipts as total_deliveries_count',
            ])
            ->with([
                'deliveryReceipts' => fn ($q) => $q
                    ->whereIn('status', $activeDeliveryStatuses)
                    ->select('id', 'ulid', 'dr_reference', 'status', 'direction', 'vehicle_id', 'driver_name', 'receipt_date')
                    ->orderByDesc('receipt_date')
                    ->limit(5),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($vehicle) {
                $v = $vehicle->toArray();
                $v['availability'] = $vehicle->active_deliveries_count > 0 ? 'in_delivery' : 'available';
                $v['current_delivery'] = $vehicle->deliveryReceipts->first();

                // Last completed delivery
                $lastCompleted = \App\Domains\Delivery\Models\DeliveryReceipt::query()
                    ->where('vehicle_id', $vehicle->id)
                    ->where('status', 'delivered')
                    ->orderByDesc('updated_at')
                    ->select('ulid', 'dr_reference', 'updated_at')
                    ->first();
                $v['last_completed_delivery'] = $lastCompleted;

                // Remove the eager-loaded relationship array to keep response clean
                unset($v['delivery_receipts']);
                return $v;
            });

        return response()->json(['data' => $vehicles]);
    })->name('vehicles.index');

    Route::post('/vehicles', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
        abort_unless($request->user()?->hasPermissionTo('delivery.manage'), 403, 'Unauthorized');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:truck,van,pickup,motorcycle,trailer,other'],
            'make_model' => ['nullable', 'string', 'max:100'],
            'plate_number' => ['required', 'string', 'max:20'],
            'status' => ['sometimes', 'string', 'in:active,inactive,maintenance,decommissioned'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $vehicle = \App\Domains\Delivery\Models\Vehicle::create(array_merge($data, ['status' => $data['status'] ?? 'active']));
        return response()->json(['data' => $vehicle], 201);
    })->middleware('throttle:api-action')->name('vehicles.store');

    Route::patch('/vehicles/{vehicle}', function (\Illuminate\Http\Request $request, \App\Domains\Delivery\Models\Vehicle $vehicle): \Illuminate\Http\JsonResponse {
        abort_unless($request->user()?->hasPermissionTo('delivery.manage'), 403, 'Unauthorized');
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'string', 'in:truck,van,pickup,motorcycle,trailer,other'],
            'make_model' => ['nullable', 'string', 'max:100'],
            'plate_number' => ['sometimes', 'string', 'max:20'],
            'status' => ['sometimes', 'string', 'in:active,inactive,maintenance,decommissioned'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $vehicle->update($data);
        return response()->json(['data' => $vehicle->fresh()]);
    })->middleware('throttle:api-action')->name('vehicles.update');

    Route::post('/receipts/{deliveryReceipt}/prepare-shipment', [DeliveryController::class, 'prepareShipment'])
        ->middleware('throttle:30,1');
    Route::patch('/receipts/{deliveryReceipt}/dispatch', [DeliveryController::class, 'markDispatched'])
        ->middleware('throttle:30,1');
    Route::patch('/receipts/{deliveryReceipt}/partial-deliver', [DeliveryController::class, 'markPartiallyDelivered'])
        ->middleware('throttle:30,1');
    Route::patch('/receipts/{deliveryReceipt}/deliver', [DeliveryController::class, 'markDelivered'])
        ->middleware('throttle:30,1');

    // Shipments
    Route::get('/shipments', [DeliveryController::class, 'indexShipments']);
    Route::post('/shipments', [DeliveryController::class, 'storeShipment']);
    Route::get('/shipments/{shipment}', [DeliveryController::class, 'showShipment']);
    Route::patch('/shipments/{shipment}/status', [DeliveryController::class, 'updateShipmentStatus']);

    // ── Delivery Export (CSV) ────────────────────────────────────────────────
    Route::get('/export', function (Request $request): StreamedResponse {
        abort_unless(auth()->user()?->hasPermissionTo('delivery.view'), 403, 'Unauthorized');
        $query = DB::table('shipments')
            ->leftJoin('delivery_receipts', 'shipments.delivery_receipt_id', '=', 'delivery_receipts.id')
            ->select(
                'shipments.tracking_number',
                'shipments.carrier as destination',
                'shipments.status',
                'shipments.estimated_arrival as scheduled_date',
                'shipments.actual_arrival as actual_delivery_date',
                'delivery_receipts.dr_reference as dr_number',
                'shipments.created_at',
            );

        if ($request->filled('status')) {
            $query->where('shipments.status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->where('shipments.estimated_arrival', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('shipments.estimated_arrival', '<=', $request->input('date_to'));
        }

        $rows = $query->orderBy('shipments.estimated_arrival', 'desc')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Tracking #', 'Destination', 'Status', 'Scheduled Date', 'Actual Delivery', 'DR Number', 'Created']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->tracking_number, $r->destination, $r->status, $r->scheduled_date, $r->actual_delivery_date, $r->dr_number, $r->created_at]);
            }
            fclose($out);
        }, 'delivery_export_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    })->name('export');

    // ── Delivery Routes (Phase 3) ─────────────────────────────────────────
    Route::get('routes', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
        abort_unless($request->user()?->hasPermissionTo('delivery.view'), 403, 'Unauthorized');
        $routes = \App\Domains\Delivery\Models\DeliveryRoute::with('createdBy')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('planned_date')
            ->paginate((int) ($request->input('per_page', 20)));
        return response()->json($routes);
    })->name('routes.index');

    Route::post('routes', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
        abort_unless($request->user()?->hasPermissionTo('delivery.manage'), 403, 'Unauthorized');
        $data = $request->validate([
            'planned_date' => ['required', 'date'],
            'vehicle_id' => ['sometimes', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['sometimes', 'integer', 'exists:employees,id'],
            'notes' => ['sometimes', 'string'],
        ]);
        $route = \App\Domains\Delivery\Models\DeliveryRoute::create([
            ...$data,
            'route_number' => 'DR-' . now()->format('Ymd-His'),
            'status' => 'planned',
            'created_by_id' => $request->user()->id,
        ]);
        return response()->json(['data' => $route], 201);
    })->name('routes.store');
});
