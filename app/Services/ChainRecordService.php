<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\AP\Models\VendorInvoice;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\QC\Models\Inspection;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;

/**
 * Chain Record Service — traces the complete document chain for any ERP document.
 *
 * Given a document type and ID, walks upstream (parent) and downstream (children)
 * to build a full traceability timeline. Covers the entire order-to-cash and
 * procure-to-pay cycles.
 *
 * Chain: ClientOrder -> DeliverySchedule -> ProductionOrder -> MRQ -> PR -> PO -> GR -> VendorInvoice
 *                                                                                   -> Inspection
 *        DeliverySchedule -> DeliveryReceipt -> CustomerInvoice
 */
final class ChainRecordService implements ServiceContract
{
    /**
     * Build the full chain record for a given document.
     *
     * @return list<array{type: string, id: int, reference: string, status: string, date: string, actor: string|null, url: string|null}>
     */
    public function trace(string $documentType, int $documentId): array
    {
        $chain = new Collection();

        match ($documentType) {
            'client_order' => $this->traceFromClientOrder($documentId, $chain),
            'delivery_schedule' => $this->traceFromDeliverySchedule($documentId, $chain),
            'production_order' => $this->traceFromProductionOrder($documentId, $chain),
            'material_requisition' => $this->traceFromMaterialRequisition($documentId, $chain),
            'purchase_request' => $this->traceFromPurchaseRequest($documentId, $chain),
            'purchase_order' => $this->traceFromPurchaseOrder($documentId, $chain),
            'goods_receipt' => $this->traceFromGoodsReceipt($documentId, $chain),
            'vendor_invoice' => $this->traceFromVendorInvoice($documentId, $chain),
            'delivery_receipt' => $this->traceFromDeliveryReceipt($documentId, $chain),
            'customer_invoice' => $this->traceFromCustomerInvoice($documentId, $chain),
            default => [],
        };

        // Deduplicate by type+id and sort by date
        return $chain
            ->unique(fn (array $item) => $item['type'] . ':' . $item['id'])
            ->sortBy('date')
            ->values()
            ->all();
    }

    // ── Trace entry points ─────────────────────────────────────────────

    private function traceFromClientOrder(int $id, Collection $chain): void
    {
        $co = ClientOrder::find($id);
        if (! $co) {
            return;
        }

        $this->addNode($chain, 'client_order', $co->id, $co->order_number ?? "CO-{$co->id}", $co->status, $co->created_at, $co->requestedBy?->name);

        // Downstream: production orders
        $prodOrders = ProductionOrder::where('client_order_id', $co->id)->get();
        foreach ($prodOrders as $po) {
            $this->traceFromProductionOrder($po->id, $chain);
        }

        // Downstream: delivery schedules via combined delivery schedule
        $deliverySchedules = DeliverySchedule::whereHas('combinedDeliverySchedule', fn ($q) => $q->where('client_order_id', $co->id))->get();
        foreach ($deliverySchedules as $ds) {
            $this->traceFromDeliverySchedule($ds->id, $chain);
        }
    }

    private function traceFromDeliverySchedule(int $id, Collection $chain): void
    {
        $ds = DeliverySchedule::with('customer')->find($id);
        if (! $ds || $chain->contains(fn ($n) => $n['type'] === 'delivery_schedule' && $n['id'] === $id)) {
            return;
        }

        $this->addNode($chain, 'delivery_schedule', $ds->id, $ds->ds_reference ?? "DS-{$ds->id}", $ds->status, $ds->created_at, null);

        // Upstream: client order via combined delivery schedule
        if ($ds->combined_delivery_schedule_id) {
            $combined = $ds->combinedDeliverySchedule;
            if ($combined?->client_order_id) {
                $this->traceFromClientOrder($combined->client_order_id, $chain);
            }
        }

        // Downstream: production orders
        $prodOrders = ProductionOrder::where('delivery_schedule_id', $ds->id)->get();
        foreach ($prodOrders as $po) {
            $this->traceFromProductionOrder($po->id, $chain);
        }

        // Downstream: delivery receipts
        $drs = DeliveryReceipt::where('delivery_schedule_id', $ds->id)->get();
        foreach ($drs as $dr) {
            $this->traceFromDeliveryReceipt($dr->id, $chain);
        }
    }

    private function traceFromProductionOrder(int $id, Collection $chain): void
    {
        $po = ProductionOrder::with('createdBy')->find($id);
        if (! $po || $chain->contains(fn ($n) => $n['type'] === 'production_order' && $n['id'] === $id)) {
            return;
        }

        $this->addNode($chain, 'production_order', $po->id, $po->po_reference ?? "PROD-{$po->id}", $po->status, $po->created_at, $po->createdBy?->name);

        // Upstream: delivery schedule
        if ($po->delivery_schedule_id) {
            $this->traceFromDeliverySchedule($po->delivery_schedule_id, $chain);
        }

        // Upstream: client order
        if ($po->client_order_id) {
            $this->traceFromClientOrder($po->client_order_id, $chain);
        }

        // Downstream: material requisitions
        $mrqs = MaterialRequisition::where('production_order_id', $po->id)->get();
        foreach ($mrqs as $mrq) {
            $this->traceFromMaterialRequisition($mrq->id, $chain);
        }

        // Downstream: QC inspections
        $inspections = Inspection::where('production_order_id', $po->id)->get();
        foreach ($inspections as $insp) {
            $this->addNode($chain, 'inspection', $insp->id, $insp->inspection_reference ?? "INS-{$insp->id}", $insp->status, $insp->created_at, null);
        }
    }

    private function traceFromMaterialRequisition(int $id, Collection $chain): void
    {
        $mrq = MaterialRequisition::find($id);
        if (! $mrq || $chain->contains(fn ($n) => $n['type'] === 'material_requisition' && $n['id'] === $id)) {
            return;
        }

        $this->addNode($chain, 'material_requisition', $mrq->id, $mrq->mr_reference ?? "MRQ-{$mrq->id}", $mrq->status, $mrq->created_at, null);

        // Upstream: production order
        if ($mrq->production_order_id) {
            $this->traceFromProductionOrder($mrq->production_order_id, $chain);
        }

        // Downstream: purchase requests linked to this MRQ
        $prs = PurchaseRequest::where('material_requisition_id', $mrq->id)->get();
        foreach ($prs as $pr) {
            $this->traceFromPurchaseRequest($pr->id, $chain);
        }
    }

    private function traceFromPurchaseRequest(int $id, Collection $chain): void
    {
        $pr = PurchaseRequest::with('requestedBy')->find($id);
        if (! $pr || $chain->contains(fn ($n) => $n['type'] === 'purchase_request' && $n['id'] === $id)) {
            return;
        }

        $this->addNode($chain, 'purchase_request', $pr->id, $pr->pr_reference, $pr->status, $pr->created_at, $pr->requestedBy?->name);

        // Upstream: material requisition
        if ($pr->material_requisition_id) {
            $this->traceFromMaterialRequisition($pr->material_requisition_id, $chain);
        }

        // Downstream: purchase orders
        $pos = PurchaseOrder::where('purchase_request_id', $pr->id)->get();
        foreach ($pos as $po) {
            $this->traceFromPurchaseOrder($po->id, $chain);
        }
    }

    private function traceFromPurchaseOrder(int $id, Collection $chain): void
    {
        $po = PurchaseOrder::with(['createdBy', 'vendor'])->find($id);
        if (! $po || $chain->contains(fn ($n) => $n['type'] === 'purchase_order' && $n['id'] === $id)) {
            return;
        }

        $actor = $po->vendor?->name ?? $po->createdBy?->name;
        $this->addNode($chain, 'purchase_order', $po->id, $po->po_reference, $po->status, $po->created_at, $actor);

        // Upstream: purchase request
        if ($po->purchase_request_id) {
            $this->traceFromPurchaseRequest($po->purchase_request_id, $chain);
        }

        // Downstream: goods receipts
        $grs = GoodsReceipt::where('purchase_order_id', $po->id)->get();
        foreach ($grs as $gr) {
            $this->traceFromGoodsReceipt($gr->id, $chain);
        }

        // Downstream: vendor invoices
        $vis = VendorInvoice::where('purchase_order_id', $po->id)->get();
        foreach ($vis as $vi) {
            $this->traceFromVendorInvoice($vi->id, $chain);
        }
    }

    private function traceFromGoodsReceipt(int $id, Collection $chain): void
    {
        $gr = GoodsReceipt::with('receivedBy')->find($id);
        if (! $gr || $chain->contains(fn ($n) => $n['type'] === 'goods_receipt' && $n['id'] === $id)) {
            return;
        }

        $this->addNode($chain, 'goods_receipt', $gr->id, $gr->gr_reference, $gr->status, $gr->created_at, $gr->receivedBy?->name);

        // Upstream: purchase order
        if ($gr->purchase_order_id) {
            $this->traceFromPurchaseOrder($gr->purchase_order_id, $chain);
        }

        // Downstream: QC inspections linked to GR
        $inspections = Inspection::where('goods_receipt_id', $gr->id)->get();
        foreach ($inspections as $insp) {
            $this->addNode($chain, 'inspection', $insp->id, $insp->inspection_reference ?? "INS-{$insp->id}", $insp->status, $insp->created_at, null);
        }
    }

    private function traceFromVendorInvoice(int $id, Collection $chain): void
    {
        $vi = VendorInvoice::find($id);
        if (! $vi || $chain->contains(fn ($n) => $n['type'] === 'vendor_invoice' && $n['id'] === $id)) {
            return;
        }

        $this->addNode($chain, 'vendor_invoice', $vi->id, $vi->invoice_reference ?? "VI-{$vi->id}", $vi->status, $vi->created_at, null);

        // Upstream: purchase order
        if ($vi->purchase_order_id) {
            $this->traceFromPurchaseOrder($vi->purchase_order_id, $chain);
        }
    }

    private function traceFromDeliveryReceipt(int $id, Collection $chain): void
    {
        $dr = DeliveryReceipt::find($id);
        if (! $dr || $chain->contains(fn ($n) => $n['type'] === 'delivery_receipt' && $n['id'] === $id)) {
            return;
        }

        $this->addNode($chain, 'delivery_receipt', $dr->id, $dr->dr_reference ?? "DR-{$dr->id}", $dr->status, $dr->created_at, null);

        // Upstream: delivery schedule
        if ($dr->delivery_schedule_id) {
            $this->traceFromDeliverySchedule($dr->delivery_schedule_id, $chain);
        }

        // Downstream: customer invoice
        $cis = CustomerInvoice::where('delivery_receipt_id', $dr->id)->get();
        foreach ($cis as $ci) {
            $this->traceFromCustomerInvoice($ci->id, $chain);
        }
    }

    private function traceFromCustomerInvoice(int $id, Collection $chain): void
    {
        $ci = CustomerInvoice::find($id);
        if (! $ci || $chain->contains(fn ($n) => $n['type'] === 'customer_invoice' && $n['id'] === $id)) {
            return;
        }

        $this->addNode($chain, 'customer_invoice', $ci->id, $ci->invoice_number ?? "CI-{$ci->id}", $ci->status, $ci->created_at, null);

        // Upstream: delivery receipt
        if ($ci->delivery_receipt_id) {
            $this->traceFromDeliveryReceipt($ci->delivery_receipt_id, $chain);
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function addNode(
        Collection $chain,
        string $type,
        int $id,
        string $reference,
        string $status,
        mixed $date,
        ?string $actor,
    ): void {
        $chain->push([
            'type' => $type,
            'id' => $id,
            'reference' => $reference,
            'status' => $status,
            'date' => $date ? (string) $date : now()->toIso8601String(),
            'actor' => $actor,
            'url' => $this->buildUrl($type, $id),
        ]);
    }

    private function buildUrl(string $type, int $id): ?string
    {
        // Look up the ULID for the model to build frontend URL
        $model = match ($type) {
            'client_order' => ClientOrder::find($id),
            'delivery_schedule' => DeliverySchedule::find($id),
            'production_order' => ProductionOrder::find($id),
            'material_requisition' => MaterialRequisition::find($id),
            'purchase_request' => PurchaseRequest::find($id),
            'purchase_order' => PurchaseOrder::find($id),
            'goods_receipt' => GoodsReceipt::find($id),
            'vendor_invoice' => VendorInvoice::find($id),
            'delivery_receipt' => DeliveryReceipt::find($id),
            'customer_invoice' => CustomerInvoice::find($id),
            'inspection' => Inspection::find($id),
            default => null,
        };

        if (! $model) {
            return null;
        }

        $ulid = $model->ulid ?? null;
        if (! $ulid) {
            return null;
        }

        return match ($type) {
            'client_order' => "/crm/orders/{$ulid}",
            'delivery_schedule' => "/production/delivery-schedules/{$ulid}",
            'production_order' => "/production/orders/{$ulid}",
            'material_requisition' => "/inventory/mrq/{$ulid}",
            'purchase_request' => "/procurement/purchase-requests/{$ulid}",
            'purchase_order' => "/procurement/purchase-orders/{$ulid}",
            'goods_receipt' => "/procurement/goods-receipts/{$ulid}",
            'vendor_invoice' => "/accounting/ap/invoices/{$ulid}",
            'delivery_receipt' => "/delivery/receipts/{$ulid}",
            'customer_invoice' => "/ar/invoices/{$ulid}",
            'inspection' => "/qc/inspections/{$ulid}",
            default => null,
        };
    }
}
