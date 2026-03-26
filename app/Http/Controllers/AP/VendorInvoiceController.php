<?php

declare(strict_types=1);

namespace App\Http\Controllers\AP;

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Domains\AP\Services\VendorInvoiceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AP\CreateVendorInvoiceRequest;
use App\Http\Requests\AP\RecordPaymentRequest;
use App\Http\Resources\AP\VendorInvoiceResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class VendorInvoiceController extends Controller
{
    public function __construct(
        private readonly VendorInvoiceService $service,
    ) {}

    /**
     * List AP invoices with optional filters:
     *   ?status=draft|pending_approval|approved|partially_paid|paid
     *   ?vendor_id=X
     *   ?fiscal_period_id=X
     *   ?due_soon=1  (overdue + due within ap.due_date_alert_days)
     *   ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VendorInvoice::class);

        $query = VendorInvoice::with(['vendor', 'fiscalPeriod'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->input('status')),
            )
            ->when(
                $request->filled('vendor_id'),
                fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')),
            )
            ->when(
                $request->filled('fiscal_period_id'),
                fn ($q) => $q->where('fiscal_period_id', $request->integer('fiscal_period_id')),
            )
            ->when(
                $request->boolean('due_soon'),
                fn ($q) => $q->dueSoon(),
            )
            ->when(
                $request->filled('date_from'),
                fn ($q) => $q->whereDate('invoice_date', '>=', $request->input('date_from')),
            )
            ->when(
                $request->filled('date_to'),
                fn ($q) => $q->whereDate('invoice_date', '<=', $request->input('date_to')),
            )
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');

        return VendorInvoiceResource::collection($query->paginate(30));
    }

    public function store(CreateVendorInvoiceRequest $request): VendorInvoiceResource
    {
        $this->authorize('create', VendorInvoice::class);

        $vendor = Vendor::findOrFail($request->integer('vendor_id'));

        $invoice = $this->service->create($vendor, $request->validated(), auth()->id());

        return new VendorInvoiceResource($invoice->load('vendor', 'fiscalPeriod'));
    }

    public function show(VendorInvoice $apInvoice): VendorInvoiceResource
    {
        $this->authorize('view', $apInvoice);

        return new VendorInvoiceResource(
            $apInvoice->load('vendor', 'fiscalPeriod', 'payments', 'apAccount', 'expenseAccount', 'purchaseOrder'),
        );
    }

    /** Submit draft for approval. */
    public function submit(VendorInvoice $apInvoice): VendorInvoiceResource
    {
        $this->authorize('submit', $apInvoice);

        $updated = $this->service->submit($apInvoice, auth()->id());

        return new VendorInvoiceResource($updated->load('vendor', 'fiscalPeriod'));
    }

    /** Approve a pending invoice (AP-010: SoD enforced in service). */
    public function approve(VendorInvoice $apInvoice): VendorInvoiceResource
    {
        $this->authorize('approve', $apInvoice);

        $updated = $this->service->approve($apInvoice, auth()->user());

        return new VendorInvoiceResource($updated->load('vendor', 'fiscalPeriod', 'payments'));
    }

    /** Step 2: Head notes a submitted invoice (pending_approval → head_noted). */
    public function headNote(VendorInvoice $apInvoice): VendorInvoiceResource
    {
        $this->authorize('approve', $apInvoice);

        $updated = $this->service->headNote($apInvoice, auth()->user());

        return new VendorInvoiceResource($updated->load('vendor', 'fiscalPeriod'));
    }

    /** Step 3: Manager checks a head-noted invoice (head_noted → manager_checked). */
    public function managerCheck(VendorInvoice $apInvoice): VendorInvoiceResource
    {
        $this->authorize('approve', $apInvoice);

        $updated = $this->service->managerCheck($apInvoice, auth()->user());

        return new VendorInvoiceResource($updated->load('vendor', 'fiscalPeriod'));
    }

    /** Step 4: Officer reviews a manager-checked invoice (manager_checked → officer_reviewed). */
    public function officerReview(VendorInvoice $apInvoice): VendorInvoiceResource
    {
        $this->authorize('approve', $apInvoice);

        $updated = $this->service->officerReview($apInvoice, auth()->user());

        return new VendorInvoiceResource($updated->load('vendor', 'fiscalPeriod'));
    }

    /** Reject a pending invoice back to draft. */
    public function reject(Request $request, VendorInvoice $apInvoice): VendorInvoiceResource
    {
        $this->authorize('reject', $apInvoice);

        $validated = $request->validate([
            'rejection_note' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $updated = $this->service->reject($apInvoice, auth()->user(), $validated['rejection_note']);

        return new VendorInvoiceResource($updated->load('vendor', 'fiscalPeriod'));
    }

    /** Record a payment against an approved/partially-paid invoice. */
    public function recordPayment(RecordPaymentRequest $request, VendorInvoice $apInvoice): JsonResponse
    {
        $this->authorize('recordPayment', $apInvoice);

        $payment = $this->service->recordPayment(
            invoice: $apInvoice,
            data: $request->validated(),
            userId: auth()->id(),
        );

        $apInvoice->refresh();

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'payment_date' => $payment->payment_date->toDateString(),
                'amount' => (float) $payment->amount,
                'reference_number' => $payment->reference_number,
                'payment_method' => $payment->payment_method,
            ],
            'invoice' => [
                'id' => $apInvoice->id,
                'status' => $apInvoice->status,
                'balance_due' => $apInvoice->balance_due,
                'total_paid' => $apInvoice->total_paid,
            ],
        ], 201);
    }

    /** Invoices due soon — for the AP Due Date Monitor dashboard widget. */
    public function dueSoon(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VendorInvoice::class);

        $days = $request->integer('days', 7);

        $invoices = VendorInvoice::with('vendor')
            ->dueSoon($days)
            ->orderBy('due_date')
            ->get();

        return VendorInvoiceResource::collection($invoices);
    }

    /**
     * GET /api/v1/finance/ap/dashboard
     * Summary statistics for the AP management dashboard.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VendorInvoice::class);

        $pending = VendorInvoice::where('status', 'pending_approval')->count();
        $approved = VendorInvoice::where('status', 'approved')->count();
        $overdue = VendorInvoice::whereIn('status', ['approved', 'partially_paid'])
            ->where('due_date', '<', today())
            ->count();
        $dueSoon = VendorInvoice::whereIn('status', ['approved', 'partially_paid'])
            ->whereBetween('due_date', [today(), today()->addDays(7)])
            ->count();
        $outstanding = VendorInvoice::whereIn('status', ['approved', 'partially_paid'])
            ->get()
            ->sum(fn (VendorInvoice $inv) => $inv->balance_due);

        return response()->json([
            'data' => [
                'pending_approval_count' => $pending,
                'approved_count' => $approved,
                'overdue_count' => $overdue,
                'due_soon_count' => $dueSoon,
                'outstanding_balance' => round((float) $outstanding, 2),
            ],
        ]);
    }

    /**
     * GET /api/v1/finance/ap/invoices/{apInvoice}/form-2307
     * Returns data needed to generate BIR Form 2307 (CWT certificate) for an AP invoice.
     */
    public function form2307(VendorInvoice $apInvoice): JsonResponse
    {
        $this->authorize('view', $apInvoice);

        $apInvoice->load(['vendor', 'fiscalPeriod']);

        $invoiceDate = $apInvoice->invoice_date;
        $quarter = (int) ceil($invoiceDate->month / 3);

        return response()->json([
            'data' => [
                'form_title' => 'Certificate of Creditable Withholding Tax At Source (BIR Form No. 2307)',
                'tax_year' => $invoiceDate->year,
                'quarter' => $quarter,
                'quarter_label' => "Q{$quarter} {$invoiceDate->year}",

                // Withholding agent (company)
                'agent_name' => config('company.registered_name', 'COMPANY NAME'),
                'agent_tin' => config('company.tin', '000-000-000-000'),
                'agent_address' => config('company.address', ''),

                // Income recipient (vendor)
                'payee_name' => $apInvoice->vendor?->company_name,
                'payee_tin' => $apInvoice->vendor?->tin,
                'payee_address' => $apInvoice->vendor?->address,

                // Invoice / EWT details
                'invoice_id' => $apInvoice->id,
                'invoice_number' => $apInvoice->invoice_number,
                'invoice_date' => $invoiceDate->toDateString(),
                'atc_code' => 'WC157',
                'gross_amount' => round((float) ($apInvoice->net_amount + $apInvoice->vat_amount), 2),
                'ewt_rate' => (float) $apInvoice->ewt_rate,
                'ewt_amount' => round((float) $apInvoice->ewt_amount, 2),
                'net_payable' => round((float) $apInvoice->net_payable, 2),
            ],
        ]);
    }

    /** Export AP invoice as PDF. */
    public function pdf(VendorInvoice $apInvoice): Response
    {
        $this->authorize('view', $apInvoice);

        $invoice = $apInvoice->load(['vendor', 'fiscalPeriod', 'payments', 'purchaseOrder']);

        $settings = [
            'company_name' => config('app.company_name', 'Ogami Manufacturing Corp.'),
            'company_address' => config('app.company_address', ''),
        ];

        $pdf = Pdf::loadView('ap.vendor-invoice-pdf', compact('invoice', 'settings'))
            ->setPaper('a4', 'portrait');

        $filename = 'AP-Invoice-'.($invoice->or_number ?? $invoice->id).'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * POST /api/v1/finance/ap/invoices/from-po
     * Create a draft AP Invoice from a Purchase Order.
     * Pre-populates the invoice with PO data for manual review.
     */
    public function createFromPo(Request $request): JsonResponse
    {
        $this->authorize('create', VendorInvoice::class);

        $validated = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
        ]);

        $result = $this->service->createInvoiceFromPo(
            poId: $validated['purchase_order_id'],
            userId: auth()->id(),
        );

        return response()->json([
            'data' => [
                'invoice' => new VendorInvoiceResource($result['invoice']->load('vendor', 'fiscalPeriod')),
                'po_items' => $result['po_items'],
                'po_reference' => $result['po_reference'],
                'vendor_name' => $result['vendor_name'],
            ],
        ], 201);
    }
}
