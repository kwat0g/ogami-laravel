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

    // Shipments
    Route::get('/shipments', [DeliveryController::class, 'indexShipments']);
    Route::post('/shipments', [DeliveryController::class, 'storeShipment']);
    Route::get('/shipments/{shipment}', [DeliveryController::class, 'showShipment']);
    Route::patch('/shipments/{shipment}/status', [DeliveryController::class, 'updateShipmentStatus']);

    // ── Delivery Export (CSV) ────────────────────────────────────────────────
    Route::get('/export', function (Request $request): StreamedResponse {
        abort_unless(auth()->user()?->hasPermissionTo('delivery.view'), 403, 'Unauthorized');
        $query = DB::table('shipments')
            ->leftJoin('delivery_receipts', 'shipments.id', '=', 'delivery_receipts.shipment_id')
            ->select(
                'shipments.tracking_number',
                'shipments.destination',
                'shipments.status',
                'shipments.scheduled_date',
                'shipments.actual_delivery_date',
                'delivery_receipts.dr_number',
                'shipments.created_at',
            );

        if ($request->filled('status')) {
            $query->where('shipments.status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->where('shipments.scheduled_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('shipments.scheduled_date', '<=', $request->input('date_to'));
        }

        $rows = $query->orderBy('shipments.scheduled_date', 'desc')->get();

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
});
