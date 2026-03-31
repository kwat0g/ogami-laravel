<?php

declare(strict_types=1);

namespace App\Http\Controllers\Delivery;

use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Delivery\Models\Shipment;
use App\Domains\Delivery\Services\DeliveryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\StoreDeliveryReceiptRequest;
use App\Http\Requests\Delivery\StoreShipmentRequest;
use App\Http\Resources\Delivery\DeliveryReceiptResource;
use App\Http\Resources\Delivery\ShipmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $service) {}

    // ── Delivery Receipts ─────────────────────────────────────────────────

    public function indexReceipts(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeliveryReceipt::class);

        return DeliveryReceiptResource::collection(
            $this->service->paginateReceipts($request->only(['search', 'direction', 'status', 'per_page', 'with_archived']))
        );
    }

    public function storeReceipt(StoreDeliveryReceiptRequest $request): JsonResponse
    {
        $this->authorize('create', DeliveryReceipt::class);
        $receipt = $this->service->storeReceipt($request->validated(), $request->user()->id);

        return (new DeliveryReceiptResource($receipt))->response()->setStatusCode(201);
    }

    public function showReceipt(DeliveryReceipt $deliveryReceipt): DeliveryReceiptResource
    {
        $this->authorize('view', $deliveryReceipt);

        return new DeliveryReceiptResource(
            $deliveryReceipt->loadMissing(['items.itemMaster', 'vendor', 'customer', 'receivedBy', 'shipments'])
        );
    }

    public function confirmReceipt(Request $request, DeliveryReceipt $deliveryReceipt): DeliveryReceiptResource
    {
        $this->authorize('confirm', $deliveryReceipt);

        return new DeliveryReceiptResource($this->service->confirmReceipt($deliveryReceipt, $request->user()));
    }

    public function markDispatched(Request $request, DeliveryReceipt $deliveryReceipt): DeliveryReceiptResource
    {
        $this->authorize('confirm', $deliveryReceipt);

        return new DeliveryReceiptResource($this->service->markDispatched($deliveryReceipt, $request->user()));
    }

    public function markPartiallyDelivered(Request $request, DeliveryReceipt $deliveryReceipt): DeliveryReceiptResource
    {
        $this->authorize('confirm', $deliveryReceipt);

        return new DeliveryReceiptResource($this->service->markPartiallyDelivered($deliveryReceipt, $request->user()));
    }

    public function markDelivered(Request $request, DeliveryReceipt $deliveryReceipt): DeliveryReceiptResource
    {
        $this->authorize('confirm', $deliveryReceipt);

        return new DeliveryReceiptResource($this->service->markDelivered($deliveryReceipt, $request->user()));
    }

    // ── Shipments ─────────────────────────────────────────────────────────

    public function indexShipments(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeliveryReceipt::class);

        return ShipmentResource::collection(
            $this->service->paginateShipments($request->only(['search', 'status', 'per_page', 'with_archived']))
        );
    }

    public function storeShipment(StoreShipmentRequest $request): JsonResponse
    {
        $this->authorize('create', DeliveryReceipt::class);
        $shipment = $this->service->storeShipment($request->validated(), $request->user()->id);

        return (new ShipmentResource($shipment))->response()->setStatusCode(201);
    }

    public function showShipment(Shipment $shipment): ShipmentResource
    {
        $this->authorize('viewAny', DeliveryReceipt::class);

        return new ShipmentResource(
            $shipment->loadMissing(['deliveryReceipt', 'createdBy', 'impexDocuments'])
        );
    }

    public function updateShipmentStatus(Request $request, Shipment $shipment): ShipmentResource
    {
        $this->authorize('confirm', $shipment->deliveryReceipt ?? DeliveryReceipt::class);

        $request->validate([
            'status' => ['required', 'string', 'in:pending,in_transit,delivered,cancelled'],
        ]);

        return new ShipmentResource(
            $this->service->updateShipmentStatus($shipment, (string) $request->input('status'))
        );
    }
}
