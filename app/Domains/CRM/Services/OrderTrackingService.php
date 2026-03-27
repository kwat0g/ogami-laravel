<?php

declare(strict_types=1);

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Delivery\Models\Shipment;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\QC\Models\Inspection;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;

/**
 * Order Tracking Service — provides end-to-end visibility for client orders.
 *
 * Tracks an order through: Placed → Approved → In Production → QC → Shipped → Delivered
 * Each stage shows status, dates, and relevant details.
 */
final class OrderTrackingService implements ServiceContract
{
    /**
     * Get tracking timeline for a client order.
     *
     * @return array{
     *     order_id: int,
     *     order_number: string|null,
     *     current_stage: string,
     *     timeline: Collection,
     * }
     */
    public function track(ClientOrder $order): array
    {
        $order->loadMissing(['items', 'customer']);

        $timeline = collect();

        // Stage 1: Order Placed
        $timeline->push([
            'stage' => 'order_placed',
            'label' => 'Order Placed',
            'status' => 'completed',
            'date' => (string) $order->created_at,
            'details' => "Order submitted by {$order->customer?->name}",
        ]);

        // Stage 2: Negotiation (if applicable)
        if ($order->negotiation_round > 0) {
            $negotiationStatus = in_array($order->status, ['negotiating', 'client_responded'])
                ? 'in_progress'
                : 'completed';

            $timeline->push([
                'stage' => 'negotiation',
                'label' => 'Negotiation',
                'status' => $negotiationStatus,
                'date' => null,
                'details' => "Round {$order->negotiation_round} — {$order->status}",
            ]);
        }

        // Stage 3: Approved
        if (in_array($order->status, ['approved', 'rejected', 'cancelled'])) {
            $timeline->push([
                'stage' => 'approved',
                'label' => $order->status === 'approved' ? 'Order Approved' : 'Order ' . ucfirst($order->status),
                'status' => $order->status === 'approved' ? 'completed' : 'failed',
                'date' => (string) ($order->approved_at ?? $order->updated_at),
                'details' => null,
            ]);
        } else {
            $timeline->push([
                'stage' => 'approved',
                'label' => 'Pending Approval',
                'status' => 'pending',
                'date' => null,
                'details' => null,
            ]);
        }

        // Stage 4: Production
        $productionOrders = ProductionOrder::query()
            ->where('client_order_id', $order->id)
            ->get();

        if ($productionOrders->isNotEmpty()) {
            $allCompleted = $productionOrders->every(fn ($po) => $po->status === 'completed');
            $anyInProgress = $productionOrders->contains(fn ($po) => in_array($po->status, ['in_progress', 'scheduled']));

            $prodStatus = match (true) {
                $allCompleted => 'completed',
                $anyInProgress => 'in_progress',
                default => 'pending',
            };

            $timeline->push([
                'stage' => 'production',
                'label' => 'In Production',
                'status' => $prodStatus,
                'date' => $productionOrders->first()?->created_at?->toIso8601String(),
                'details' => sprintf('%d order(s), %s',
                    $productionOrders->count(),
                    $allCompleted ? 'all completed' : ($anyInProgress ? 'in progress' : 'scheduled'),
                ),
            ]);

            // Stage 5: QC
            $inspections = Inspection::query()
                ->whereIn('production_order_id', $productionOrders->pluck('id'))
                ->get();

            if ($inspections->isNotEmpty()) {
                $allPassed = $inspections->every(fn ($i) => $i->status === 'passed');
                $anyInProgress = $inspections->contains(fn ($i) => $i->status === 'in_progress');

                $timeline->push([
                    'stage' => 'qc_inspection',
                    'label' => 'Quality Inspection',
                    'status' => $allPassed ? 'completed' : ($anyInProgress ? 'in_progress' : 'pending'),
                    'date' => $inspections->first()?->created_at?->toIso8601String(),
                    'details' => sprintf('%d inspection(s), %d passed',
                        $inspections->count(),
                        $inspections->where('status', 'passed')->count(),
                    ),
                ]);
            } else {
                $timeline->push([
                    'stage' => 'qc_inspection',
                    'label' => 'Quality Inspection',
                    'status' => 'pending',
                    'date' => null,
                    'details' => 'Awaiting inspection',
                ]);
            }
        }

        // Stage 6: Delivery
        $deliveryReceipts = DeliveryReceipt::query()
            ->where('customer_id', $order->customer_id)
            ->where('direction', 'outbound')
            ->where('created_at', '>=', $order->created_at)
            ->get();

        $shipments = Shipment::query()
            ->whereIn('delivery_receipt_id', $deliveryReceipts->pluck('id'))
            ->get();

        if ($shipments->isNotEmpty()) {
            $delivered = $shipments->contains(fn ($s) => $s->status === 'delivered');
            $inTransit = $shipments->contains(fn ($s) => $s->status === 'in_transit');

            $timeline->push([
                'stage' => 'shipped',
                'label' => $delivered ? 'Delivered' : ($inTransit ? 'In Transit' : 'Shipment Prepared'),
                'status' => $delivered ? 'completed' : ($inTransit ? 'in_progress' : 'pending'),
                'date' => $shipments->first()?->shipped_at,
                'details' => $shipments->first()?->tracking_number
                    ? "Tracking: {$shipments->first()->tracking_number}"
                    : null,
            ]);
        } elseif ($deliveryReceipts->isNotEmpty()) {
            $timeline->push([
                'stage' => 'shipped',
                'label' => 'Ready for Shipment',
                'status' => 'pending',
                'date' => null,
                'details' => 'Delivery receipt created, awaiting shipment',
            ]);
        }

        // Determine current stage
        $currentStage = $timeline->last(fn ($t) => $t['status'] === 'in_progress')['stage']
            ?? $timeline->last(fn ($t) => $t['status'] === 'completed')['stage']
            ?? 'order_placed';

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number ?? null,
            'current_stage' => $currentStage,
            'timeline' => $timeline,
        ];
    }
}
