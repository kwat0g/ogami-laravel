<?php

declare(strict_types=1);

namespace App\Http\Controllers\AR;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Services\CustomerInvoiceService;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\Production\Models\DeliverySchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\AR\CreateCustomerInvoiceRequest;
use App\Http\Requests\AR\ReceivePaymentRequest;
use App\Http\Requests\AR\WriteOffRequest;
use App\Http\Resources\AR\CustomerInvoiceResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

final class CustomerInvoiceController extends Controller
{
    public function __construct(
        private readonly CustomerInvoiceService $service,
    ) {}

    /**
     * List invoices.
     *   ?customer_id=1
     *   ?status=draft|approved|partially_paid|paid|written_off|cancelled
     *   ?due_soon=7  (days)
     *   ?overdue=1
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomerInvoice::class);

        $query = CustomerInvoice::with(['customer', 'fiscalPeriod'])
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->byStatus($request->input('status')))
            ->when($request->filled('due_soon'), fn ($q) => $q->dueSoon($request->integer('due_soon', 7)))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->orderBy('invoice_date', 'desc');

        return CustomerInvoiceResource::collection($query->paginate($request->integer('per_page', 25)));
    }

    /** Invoices due within the next N days (default 7). */
    public function dueSoon(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomerInvoice::class);

        $days = $request->integer('days', 7);
        $results = CustomerInvoice::with(['customer', 'fiscalPeriod'])->dueSoon($days)->orderBy('due_date')->paginate(50);

        return CustomerInvoiceResource::collection($results);
    }

    public function store(CreateCustomerInvoiceRequest $request): CustomerInvoiceResource
    {
        $this->authorize('create', CustomerInvoice::class);

        $validated = $request->validated();
        $customer = Customer::findOrFail($validated['customer_id']);

        // AR-002: ensure bypass_credit_check is only accepted when user has permission
        if (! empty($validated['bypass_credit_check'])) {
            $this->authorize('overrideCredit', CustomerInvoice::class);
        }

        $invoice = $this->service->create($customer, $validated, auth()->id());

        return new CustomerInvoiceResource($invoice->load(['customer', 'fiscalPeriod']));
    }

    public function show(CustomerInvoice $customerInvoice): CustomerInvoiceResource
    {
        $this->authorize('view', $customerInvoice);

        return new CustomerInvoiceResource($customerInvoice->load(['customer', 'payments', 'fiscalPeriod']));
    }

    /** AR-003: approve draft → generate invoice number + auto-post JE. */
    public function approve(CustomerInvoice $customerInvoice): CustomerInvoiceResource
    {
        $this->authorize('approve', $customerInvoice);

        $invoice = $this->service->approve($customerInvoice, auth()->user());

        return new CustomerInvoiceResource($invoice->load(['customer', 'payments', 'fiscalPeriod']));
    }

    public function cancel(CustomerInvoice $customerInvoice): JsonResponse
    {
        $this->authorize('cancel', $customerInvoice);

        $this->service->cancel($customerInvoice);

        return response()->json(['message' => 'Invoice cancelled successfully.']);
    }

    /** AR-005: payment > balance_due → excess → customer_advance_payments. */
    public function receivePayment(ReceivePaymentRequest $request, CustomerInvoice $customerInvoice): JsonResponse
    {
        $this->authorize('receivePayment', $customerInvoice);

        $payment = $this->service->receivePayment($customerInvoice, $request->validated(), auth()->id());

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'data' => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'payment_date' => $payment->payment_date->toDateString(),
            ],
        ], 201);
    }

    /** Export customer invoice as PDF. */
    public function pdf(CustomerInvoice $customerInvoice): Response
    {
        $this->authorize('view', $customerInvoice);

        $invoice = $customerInvoice->load([
            'customer',
            'payments',
            'fiscalPeriod',
            'deliveryReceipt.items.itemMaster',
            'deliveryReceipt.deliverySchedule.items.productItem',
            'deliveryReceipt.deliverySchedule.clientOrder.items.itemMaster',
            'deliveryReceipt.deliverySchedule.productItem',
        ]);

        $clientOrder = $invoice->deliveryReceipt?->deliverySchedule?->clientOrder;
        $deliverySchedule = $invoice->deliveryReceipt?->deliverySchedule;

        if ($clientOrder === null || $deliverySchedule === null) {
            $refs = $this->extractSourceRefsFromDescription((string) ($invoice->description ?? ''));

            if ($clientOrder === null && $refs['client_order_ref'] !== null) {
                $clientOrder = ClientOrder::query()
                    ->with('items.itemMaster')
                    ->where('order_reference', $refs['client_order_ref'])
                    ->first();
            }

            if ($deliverySchedule === null && $refs['delivery_schedule_ref'] !== null) {
                $deliverySchedule = DeliverySchedule::query()
                    ->with(['items.productItem', 'productItem'])
                    ->where('ds_reference', $refs['delivery_schedule_ref'])
                    ->first();
            }
        }

        $pdfLines = $this->buildPdfLines($invoice, $clientOrder, $deliverySchedule);

        $settings = [
            'company_name' => config('app.company_name', 'Ogami Manufacturing Corp.'),
            'company_address' => config('app.company_address', ''),
        ];

        $pdf = Pdf::loadView('ar.customer-invoice-pdf', compact('invoice', 'settings', 'pdfLines'))
            ->setPaper('a4', 'portrait');

        $filename = 'Invoice-'.($invoice->invoice_number ?? $invoice->id).'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * @return array{client_order_ref: string|null, delivery_schedule_ref: string|null}
     */
    private function extractSourceRefsFromDescription(string $description): array
    {
        $clientOrderRef = null;
        $deliveryScheduleRef = null;

        if (preg_match('/Client Order\s+([A-Z0-9\-]+)/i', $description, $match) === 1) {
            $clientOrderRef = strtoupper((string) $match[1]);
        }

        if (preg_match('/Delivery Schedule\s+([A-Z0-9\-]+)/i', $description, $match) === 1) {
            $deliveryScheduleRef = strtoupper((string) $match[1]);
        }

        return [
            'client_order_ref' => $clientOrderRef,
            'delivery_schedule_ref' => $deliveryScheduleRef,
        ];
    }

    /**
     * @return Collection<int, array{description: string, uom: string, qty: float, unit_price: float, amount: float}>
     */
    private function buildPdfLines(CustomerInvoice $invoice, ?ClientOrder $clientOrder, ?DeliverySchedule $deliverySchedule): Collection
    {
        if ($clientOrder !== null && $clientOrder->relationLoaded('items') && $clientOrder->items->count() > 0) {
            return $clientOrder->items->map(function ($item): array {
                $qty = (float) ($item->negotiated_quantity ?? $item->quantity ?? 0);
                if ($qty <= 0) {
                    $qty = 1.0;
                }

                $unitPrice = (float) (($item->negotiated_price_centavos ?? $item->unit_price_centavos ?? 0) / 100);
                $amount = (float) (($item->line_total_centavos ?? 0) / 100);
                if ($amount <= 0 && $unitPrice > 0) {
                    $amount = $qty * $unitPrice;
                }

                return [
                    'description' => (string) ($item->itemMaster?->name ?? $item->item_description ?? 'Order item'),
                    'uom' => (string) ($item->unit_of_measure ?? 'pcs'),
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ];
            });
        }

        if ($deliverySchedule !== null && $deliverySchedule->relationLoaded('items') && $deliverySchedule->items->count() > 0) {
            return $deliverySchedule->items->map(function ($item): array {
                $qty = (float) ($item->qty_ordered ?? 0);
                if ($qty <= 0) {
                    $qty = 1.0;
                }

                $unitPrice = (float) ($item->unit_price ?? 0);

                return [
                    'description' => (string) ($item->productItem?->name ?? 'Scheduled item'),
                    'uom' => 'pcs',
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'amount' => $unitPrice > 0 ? $qty * $unitPrice : 0.0,
                ];
            });
        }

        if ($invoice->deliveryReceipt !== null && $invoice->deliveryReceipt->relationLoaded('items') && $invoice->deliveryReceipt->items->count() > 0) {
            return $invoice->deliveryReceipt->items->map(function ($item): array {
                $qty = (float) (($item->quantity_received ?? 0) > 0 ? $item->quantity_received : ($item->quantity_expected ?? 0));
                if ($qty <= 0) {
                    $qty = 1.0;
                }

                return [
                    'description' => (string) ($item->itemMaster?->name ?? $item->remarks ?? 'Delivered item'),
                    'uom' => (string) ($item->unit_of_measure ?? 'pcs'),
                    'qty' => $qty,
                    'unit_price' => 0.0,
                    'amount' => 0.0,
                ];
            });
        }

        if ($deliverySchedule !== null && $deliverySchedule->relationLoaded('productItem') && $deliverySchedule->productItem !== null) {
            $qty = (float) ($deliverySchedule->qty_ordered ?? 1);
            if ($qty <= 0) {
                $qty = 1.0;
            }

            $unitPrice = (float) ($deliverySchedule->unit_price ?? 0);

            return collect([[
                'description' => (string) $deliverySchedule->productItem->name,
                'uom' => 'pcs',
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'amount' => $unitPrice > 0 ? $qty * $unitPrice : 0.0,
            ]]);
        }

        $fallbackAmount = (float) ($invoice->subtotal ?? $invoice->total_amount ?? 0);

        return collect([[
            'description' => (string) ($invoice->description ?? 'Invoice amount'),
            'uom' => 'lot',
            'qty' => 1.0,
            'unit_price' => $fallbackAmount,
            'amount' => $fallbackAmount,
        ]]);
    }

    /** AR-006: bad debt write-off — Accounting Manager only (Policy gate). */
    public function writeOff(WriteOffRequest $request, CustomerInvoice $customerInvoice): CustomerInvoiceResource
    {
        $this->authorize('writeOff', $customerInvoice);

        $invoice = $this->service->writeOff($customerInvoice, $request->validated(), auth()->id());

        return new CustomerInvoiceResource($invoice->load(['customer', 'payments', 'fiscalPeriod']));
    }
}
