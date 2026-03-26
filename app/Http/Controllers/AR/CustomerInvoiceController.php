<?php

declare(strict_types=1);

namespace App\Http\Controllers\AR;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Services\CustomerInvoiceService;
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

        $query = CustomerInvoice::with(['customer'])
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
        $results = CustomerInvoice::with('customer')->dueSoon($days)->orderBy('due_date')->paginate(50);

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

        return new CustomerInvoiceResource($invoice->load('customer'));
    }

    public function show(CustomerInvoice $customerInvoice): CustomerInvoiceResource
    {
        $this->authorize('view', $customerInvoice);

        return new CustomerInvoiceResource($customerInvoice->load(['customer', 'payments']));
    }

    /** AR-003: approve draft → generate invoice number + auto-post JE. */
    public function approve(CustomerInvoice $customerInvoice): CustomerInvoiceResource
    {
        $this->authorize('approve', $customerInvoice);

        $invoice = $this->service->approve($customerInvoice, auth()->user());

        return new CustomerInvoiceResource($invoice->load(['customer', 'payments']));
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

        $invoice = $customerInvoice->load(['customer', 'payments', 'items']);

        $settings = [
            'company_name' => config('app.company_name', 'Ogami Manufacturing Corp.'),
            'company_address' => config('app.company_address', ''),
        ];

        $pdf = Pdf::loadView('ar.customer-invoice-pdf', compact('invoice', 'settings'))
            ->setPaper('a4', 'portrait');

        $filename = 'Invoice-'.($invoice->invoice_number ?? $invoice->id).'.pdf';

        return $pdf->stream($filename);
    }

    /** AR-006: bad debt write-off — Accounting Manager only (Policy gate). */
    public function writeOff(WriteOffRequest $request, CustomerInvoice $customerInvoice): CustomerInvoiceResource
    {
        $this->authorize('writeOff', $customerInvoice);

        $invoice = $this->service->writeOff($customerInvoice, $request->validated(), auth()->id());

        return new CustomerInvoiceResource($invoice->load(['customer', 'payments']));
    }
}
