<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalEntryService;
use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Domains\AP\Models\VendorPayment;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\Tax\Services\VatLedgerService;
use App\Models\User;
use App\Notifications\VendorInvoiceDecidedNotification;
use App\Notifications\VendorInvoiceSubmittedNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Vendor Invoice Service — AP-001 through AP-011 enforcement.
 *
 * AP-001: due_date >= invoice_date (also enforced by DB CHECK).
 * AP-002: vendor must be active.
 * AP-003: OR number required when VAT > 0.
 * AP-004: EWT computed via EwtService.
 * AP-005: net_payable = net + vat − ewt (accessor, never stored).
 * AP-006: invoices only editable in draft.
 * AP-007: total payments cannot exceed net_payable.
 * AP-008: payment amount must be > 0 (DB CHECK + service).
 * AP-009: Form 2307 tracking (via EwtService).
 * AP-010: approval SoD — approver ≠ submitter.
 * AP-011: TIN required before first payment.
 */
final class VendorInvoiceService implements ServiceContract
{
    public function __construct(
        private readonly EwtService $ewtService,
        private readonly JournalEntryService $jeService,
        private readonly FiscalPeriodService $fiscalPeriodService,
        private readonly ApPaymentPostingService $paymentPostingService,
        private readonly VatLedgerService $vatLedgerService,
    ) {}

    // ── Create (draft) ────────────────────────────────────────────────────────

    /**
     * @param array{
     *   vendor_id: int,
     *   fiscal_period_id: int,
     *   ap_account_id: int,
     *   expense_account_id: int,
     *   invoice_date: string,
     *   due_date: string,
     *   net_amount: float,
     *   vat_amount?: float,
     *   or_number?: string,
     *   vat_exemption_reason?: string,
     *   description?: string,
     * } $data
     */
    public function create(Vendor $vendor, array $data, int $userId): VendorInvoice
    {
        // AP-002
        $vendor->assertActive();

        $invoiceDate = Carbon::parse($data['invoice_date']);
        $dueDate = Carbon::parse($data['due_date']);

        // AP-001
        if ($dueDate->lt($invoiceDate)) {
            throw new DomainException(
                message: 'Due date must be on or after the invoice date. (AP-001)',
                errorCode: 'AP_DUE_DATE_BEFORE_INVOICE_DATE',
                httpStatus: 422,
            );
        }

        $vatAmount = (float) ($data['vat_amount'] ?? 0.00);

        // AP-003
        if ($vatAmount > 0 && empty($data['or_number'])) {
            throw new DomainException(
                message: 'Official receipt number (OR) is required when VAT is greater than zero. (AP-003)',
                errorCode: 'AP_OR_NUMBER_REQUIRED_FOR_VAT',
                httpStatus: 422,
            );
        }

        // AP-005: duplicate OR number check
        if (! empty($data['or_number'])) {
            $duplicate = VendorInvoice::where('vendor_id', $vendor->id)
                ->where('or_number', $data['or_number'])
                ->whereNotIn('status', ['rejected'])
                ->first();
            if ($duplicate) {
                throw new DomainException(
                    message: "Invoice with OR number '{$data['or_number']}' already exists for this vendor.",
                    errorCode: 'DUPLICATE_INVOICE',
                    httpStatus: 422,
                );
            }
        }

        // AP-004: compute EWT snapshot
        $ewtAmount = $this->ewtService->computeForInvoice(
            vendor: $vendor,
            netAmount: (float) $data['net_amount'],
            invoiceDate: $invoiceDate,
        );

        return VendorInvoice::create([
            'vendor_id' => $vendor->id,
            'fiscal_period_id' => $data['fiscal_period_id'],
            'ap_account_id' => $data['ap_account_id'],
            'expense_account_id' => $data['expense_account_id'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'net_amount' => $data['net_amount'],
            'vat_amount' => $vatAmount,
            'ewt_amount' => $ewtAmount,
            'ewt_rate' => $vendor->is_ewt_subject ? $vendor->ewtRate?->rate : null,
            'or_number' => $data['or_number'] ?? null,
            'vat_exemption_reason' => $data['vat_exemption_reason'] ?? null,
            'atc_code' => $vendor->is_ewt_subject ? $vendor->atc_code : null,
            'description' => $data['description'] ?? null,
            'status' => 'draft',
            'created_by' => $userId,
        ]);
    }

    // ── Submit ────────────────────────────────────────────────────────────────

    public function submit(VendorInvoice $invoice, int $userId): VendorInvoice
    {
        if (! $invoice->isDraft()) {
            throw new DomainException(
                message: "Only draft invoices can be submitted. Current status: '{$invoice->status}'.",
                errorCode: 'AP_INVALID_STATUS_FOR_SUBMIT',
                httpStatus: 409,
            );
        }

        $invoice->update([
            'status' => 'pending_approval',
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ]);

        $invoice = $invoice->fresh();

        // Notify all AP approvers that a new invoice is pending their review
        $this->notifyApproversOfSubmission($invoice);

        return $invoice;
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    /**
     * Step 2 of 5: Department Head notes a submitted invoice.
     * pending_approval → head_noted
     */
    public function headNote(VendorInvoice $invoice, User $actor): VendorInvoice
    {
        if (! $invoice->isPendingApproval()) {
            throw new DomainException(
                message: "Only pending-approval invoices can be head-noted. Current status: '{$invoice->status}'.",
                errorCode: 'AP_INVALID_STATUS_FOR_HEAD_NOTE',
                httpStatus: 409,
            );
        }

        if (! $actor->hasRole('super_admin') && $invoice->submitted_by === $actor->id) {
            throw new SodViolationException(processName: 'AP Invoice', conflictingAction: 'head_note');
        }

        $invoice->update([
            'status' => 'head_noted',
            'head_noted_by' => $actor->id,
            'head_noted_at' => now(),
        ]);

        return $invoice->fresh();
    }

    /**
     * Step 3 of 5: Manager checks a head-noted invoice.
     * head_noted → manager_checked
     */
    public function managerCheck(VendorInvoice $invoice, User $actor): VendorInvoice
    {
        if (! $invoice->isHeadNoted()) {
            throw new DomainException(
                message: "Only head-noted invoices can be manager-checked. Current status: '{$invoice->status}'.",
                errorCode: 'AP_INVALID_STATUS_FOR_MANAGER_CHECK',
                httpStatus: 409,
            );
        }

        if (! $actor->hasRole('super_admin') && $invoice->head_noted_by === $actor->id) {
            throw new SodViolationException(processName: 'AP Invoice', conflictingAction: 'manager_check');
        }

        $invoice->update([
            'status' => 'manager_checked',
            'manager_checked_by' => $actor->id,
            'manager_checked_at' => now(),
        ]);

        return $invoice->fresh();
    }

    /**
     * Step 4 of 5: Officer reviews a manager-checked invoice.
     * manager_checked → officer_reviewed
     */
    public function officerReview(VendorInvoice $invoice, User $actor): VendorInvoice
    {
        if (! $invoice->isManagerChecked()) {
            throw new DomainException(
                message: "Only manager-checked invoices can be officer-reviewed. Current status: '{$invoice->status}'.",
                errorCode: 'AP_INVALID_STATUS_FOR_OFFICER_REVIEW',
                httpStatus: 409,
            );
        }

        if (! $actor->hasRole('super_admin') && $invoice->manager_checked_by === $actor->id) {
            throw new SodViolationException(processName: 'AP Invoice', conflictingAction: 'officer_review');
        }

        $invoice->update([
            'status' => 'officer_reviewed',
            'officer_reviewed_by' => $actor->id,
            'officer_reviewed_at' => now(),
        ]);

        return $invoice->fresh();
    }

    /**
     * Approve a pending invoice and auto-post the corresponding JE.
     * AP-010: approver must not be the same person who submitted.
     */
    public function approve(VendorInvoice $invoice, User $actor): VendorInvoice
    {
        if (! $invoice->isOfficerReviewed()) {
            throw new DomainException(
                message: "Only officer-reviewed invoices can be approved (VP step 5). Current status: '{$invoice->status}'.",
                errorCode: 'AP_INVALID_STATUS_FOR_APPROVE',
                httpStatus: 409,
            );
        }

        // AP-010 SoD: approver ≠ submitter
        if (! $actor->hasRole('super_admin') && $invoice->submitted_by === $actor->id) {
            throw new SodViolationException(
                processName: 'AP Invoice',
                conflictingAction: 'approve',
            );
        }

        $invoice = DB::transaction(function () use ($invoice, $actor) {
            $invoice->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            // Auto-post JE only once (idempotency guard)
            if (is_null($invoice->journal_entry_id)) {
                $this->autoPostJournalEntry($invoice);
            }

            // TAX-INPUT-001: Accumulate input VAT so the VatLedger correctly
            // reflects deductible input VAT for the period (net_vat = output - input).
            if ((float) $invoice->vat_amount > 0) {
                $this->vatLedgerService->accumulateInputVat(
                    fiscalPeriodId: (int) $invoice->fiscal_period_id,
                    amount: (float) $invoice->vat_amount,
                );
            }

            return $invoice->fresh();
        });

        // Notify the submitter their invoice was approved and posted to GL
        $this->notifySubmitterOfDecision($invoice, 'approved', null);

        return $invoice;
    }

    // ── Reject ────────────────────────────────────────────────────────────────

    public function reject(VendorInvoice $invoice, User $actor, string $note): VendorInvoice
    {
        if (! $invoice->isPendingApproval()) {
            throw new DomainException(
                message: "Only pending-approval invoices can be rejected. Current status: '{$invoice->status}'.",
                errorCode: 'AP_INVALID_STATUS_FOR_REJECT',
                httpStatus: 409,
            );
        }

        if (! $actor->hasRole('super_admin') && $invoice->submitted_by === $actor->id) {
            throw new SodViolationException(
                processName: 'AP Invoice',
                conflictingAction: 'reject',
            );
        }

        $invoice->update([
            'status' => 'draft',
            'rejection_note' => $note,
        ]);

        $invoice = $invoice->fresh();

        // Notify the submitter their invoice was returned to draft for revision
        $this->notifySubmitterOfDecision($invoice, 'rejected', $note);

        return $invoice;
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    /** AP-006: only draft or pending_approval invoices can be cancelled. */
    public function cancel(VendorInvoice $invoice): void
    {
        if (! in_array($invoice->status, ['draft', 'pending_approval'], true)) {
            throw new DomainException(
                message: "Invoices with status '{$invoice->status}' cannot be cancelled.",
                errorCode: 'AP_INVOICE_NOT_CANCELLABLE',
                httpStatus: 409,
            );
        }

        $invoice->update(['status' => 'deleted']);
        $invoice->delete();
    }

    // ── Private notification helpers ──────────────────────────────────────────

    /**
     * Notify all users with vendor_invoices.approve permission that a new invoice needs review.
     * Excludes the submitter themselves (SoD).
     */
    private function notifyApproversOfSubmission(VendorInvoice $invoice): void
    {
        try {
            $invoice->loadMissing('vendor');
            User::permission('vendor_invoices.approve')
                ->where('id', '!=', $invoice->submitted_by)
                ->each(fn (User $u) => $u->notify(VendorInvoiceSubmittedNotification::fromModel($invoice)));
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Notify the invoice submitter of the approval outcome (approved or rejected).
     */
    private function notifySubmitterOfDecision(VendorInvoice $invoice, string $decision, ?string $rejectionNote): void
    {
        try {
            if (! $invoice->submitted_by) {
                return;
            }

            $invoice->loadMissing('vendor');
            User::find($invoice->submitted_by)?->notify(
                VendorInvoiceDecidedNotification::fromModel($invoice, $decision, $rejectionNote)
            );
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    // ── Record Payment ────────────────────────────────────────────────────────

    /**
     * @param array{
     *   amount: float,
     *   payment_date: string,
     *   reference_number?: string,
     *   payment_method?: string,
     *   notes?: string,
     * } $data
     */
    public function recordPayment(VendorInvoice $invoice, array $data, int $userId): VendorPayment
    {
        if (! in_array($invoice->status, ['approved', 'partially_paid'], true)) {
            throw new DomainException(
                message: "Payments can only be recorded for approved or partially-paid invoices. Current status: '{$invoice->status}'.",
                errorCode: 'AP_INVALID_STATUS_FOR_PAYMENT',
                httpStatus: 409,
            );
        }

        $amount = (float) $data['amount'];

        // AP-008: positive amount (DB CHECK also guards this)
        if ($amount <= 0) {
            throw new DomainException(
                message: 'Payment amount must be greater than zero. (AP-008)',
                errorCode: 'AP_PAYMENT_AMOUNT_NOT_POSITIVE',
                httpStatus: 422,
            );
        }

        // AP-007: total paid + new amount must not exceed net_payable
        $newTotal = $invoice->total_paid + $amount;
        if ($newTotal > ($invoice->net_payable + 0.005)) { // 0.005 rounding tolerance
            throw new DomainException(
                message: sprintf(
                    'Payment of ₱%s would exceed the balance due of ₱%s. (AP-007)',
                    number_format($amount, 2),
                    number_format($invoice->balance_due, 2),
                ),
                errorCode: 'AP_PAYMENT_EXCEEDS_BALANCE',
                httpStatus: 422,
            );
        }

        // AP-011: TIN required before first payment
        if (! $invoice->vendor->hasTin()) {
            throw new DomainException(
                message: "A TIN must be set for vendor '{$invoice->vendor->name}' before recording any payment. (AP-011)",
                errorCode: 'AP_VENDOR_TIN_REQUIRED_FOR_PAYMENT',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($invoice, $data, $userId, $amount, $newTotal) {
            $payment = VendorPayment::create([
                'vendor_invoice_id' => $invoice->id,
                'vendor_id' => $invoice->vendor_id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // Update invoice status
            $isFullyPaid = abs($invoice->net_payable - $newTotal) <= 0.005;
            $invoice->update([
                'status' => $isFullyPaid ? 'paid' : 'partially_paid',
            ]);

            // Post GL entry: DR AP Payable / CR Cash
            $this->paymentPostingService->postApPayment($payment);

            return $payment;
        });
    }

    // ── Auto-post JE (AP-005) ─────────────────────────────────────────────────

    /**
     * Creates and immediately posts a JE for an approved AP invoice.
     * JE lines: DR expense_account / CR ap_account for gross amount.
     * source_type = 'ap' so the SoD check in JournalEntryService is skipped.
     */
    private function autoPostJournalEntry(VendorInvoice $invoice): void
    {
        $grossAmount = (float) $invoice->net_amount + (float) $invoice->vat_amount;

        $je = $this->jeService->create([
            'date' => $invoice->invoice_date->toDateString(),
            'description' => "AP: {$invoice->vendor->name} — ".($invoice->description ?? $invoice->or_number ?? "Invoice #{$invoice->id}"),
            'source_type' => 'ap',
            'source_id' => $invoice->id,
            'lines' => [
                [
                    'account_id' => $invoice->expense_account_id,
                    'debit' => $grossAmount,
                    'credit' => null,
                    'description' => "Expense — {$invoice->vendor->name}",
                ],
                [
                    'account_id' => $invoice->ap_account_id,
                    'debit' => null,
                    'credit' => $grossAmount,
                    'description' => "AP payable — {$invoice->vendor->name}",
                ],
            ],
        ]);

        $this->jeService->post($je);

        $invoice->update(['journal_entry_id' => $je->id]);
    }

    // ── Auto-create from Procurement (Sprint 4) ──────────────────────────────

    /**
     * Auto-create a draft AP Invoice after three-way match passes.
     *
     * Created in 'draft' status — the Accounting Officer must fill in the GL
     * accounts (ap_account_id, expense_account_id) and submit for approval.
     * SOD-009 still applies at submission time.
     *
     * @throws \RuntimeException when no open fiscal period exists
     */
    public function createFromPo(GoodsReceipt $gr, ?int $actorId): VendorInvoice
    {
        $po = $gr->purchaseOrder()->with(['vendor', 'vendor.ewtRate'])->firstOrFail();
        $vendor = $po->vendor;

        if ($vendor === null) {
            throw new \RuntimeException('PO has no associated vendor — cannot auto-create AP invoice.');
        }

        /** @var FiscalPeriod|null $fiscalPeriod */
        $fiscalPeriod = FiscalPeriod::open()->latest('date_from')->first();

        if ($fiscalPeriod === null) {
            throw new \RuntimeException('No open fiscal period found — cannot auto-create AP invoice.');
        }

        // Parse payment terms for due date (e.g. "Net 30" → +30 days)
        $paymentDays = 30;
        if (filled($vendor->payment_terms)) {
            preg_match('/\d+/', (string) $vendor->payment_terms, $matches);
            if (! empty($matches)) {
                $paymentDays = (int) $matches[0];
            }
        }

        $invoiceDate = now()->toDateString();
        $dueDate = now()->addDays($paymentDays)->toDateString();

        // Compute net amount from this GR's actual received quantities × agreed unit cost.
        // Falls back to PO total only if no items are loaded (safety net).
        $gr->loadMissing('items.poItem');
        $netAmount = $gr->items->reduce(
            fn (float $carry, GoodsReceiptItem $item): float => $carry + ((float) $item->quantity_received * (float) ($item->poItem->agreed_unit_cost ?? 0)),
            0.0,
        );
        if ($netAmount <= 0) {
            $netAmount = (float) $po->total_po_amount;
        }

        // D2: Resolve default GL accounts so the invoice is immediately postable.
        // AP Payable — CoA code 2001; Expense — check vendor's preferred account first.
        $apAccount = ChartOfAccount::where('code', '2001')->first();
        $expenseAccount = ChartOfAccount::where('code', '6001')->first();

        $ewtAmount = $vendor->is_ewt_subject
            ? $this->ewtService->computeForInvoice(
                vendor: $vendor,
                netAmount: $netAmount,
                invoiceDate: Carbon::parse($invoiceDate),
            )
            : 0.00;

        $invoice = VendorInvoice::create([
            'vendor_id' => $vendor->id,
            'fiscal_period_id' => $fiscalPeriod->id,
            'ap_account_id' => $apAccount?->id,
            'expense_account_id' => $expenseAccount?->id,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'net_amount' => $netAmount,
            'vat_amount' => 0.00,
            'ewt_amount' => $ewtAmount,
            'ewt_rate' => $vendor->is_ewt_subject ? $vendor->ewtRate?->rate : null,
            'atc_code' => $vendor->is_ewt_subject ? $vendor->atc_code : null,
            'description' => "Auto-created from GR {$gr->gr_reference} / PO {$po->po_reference}",
            'source' => 'auto_procurement',
            'purchase_order_id' => $po->id,
            'status' => 'draft',
            'created_by' => $actorId ?? 1,
        ]);

        // Mark the GR so we don't create a duplicate invoice on retry
        $gr->update(['ap_invoice_created' => true]);

        return $invoice;
    }

    // ── Create from Purchase Order (Manual) ──────────────────────────────────

    /**
     * Create a draft AP Invoice from a Purchase Order for manual data entry.
     * This differs from the GR-based auto-creation above — it pre-populates
     * the invoice form with PO data for the user to review and adjust.
     *
     * @param  int  $poId  The Purchase Order ID
     * @param  int  $userId  The user creating the invoice
     * @return array{invoice: VendorInvoice, po_items: array} Pre-populated invoice data
     *
     * @throws DomainException When PO not found, invalid status, or invoice already exists
     */
    public function createInvoiceFromPo(int $poId, int $userId): array
    {
        // Find the PO with vendor and items
        $po = PurchaseOrder::with(['vendor', 'vendor.ewtRate', 'items'])
            ->find($poId);

        if ($po === null) {
            throw new DomainException(
                message: 'Purchase order not found.',
                errorCode: 'AP_PO_NOT_FOUND',
                httpStatus: 404,
            );
        }

        // Verify PO status is eligible for invoicing (sent or partially_received)
        if (! in_array($po->status, ['sent', 'partially_received'], true)) {
            throw new DomainException(
                message: "Cannot create invoice from PO with status '{$po->status}'. PO must be 'sent' or 'partially_received'.",
                errorCode: 'AP_PO_INVALID_STATUS',
                httpStatus: 422,
            );
        }

        // Check if an invoice already exists for this PO (prevent duplicate billing)
        $existingInvoice = VendorInvoice::where('purchase_order_id', $po->id)
            ->whereIn('status', ['draft', 'pending_approval', 'approved', 'partially_paid'])
            ->first();

        if ($existingInvoice !== null) {
            throw new DomainException(
                message: "An invoice already exists for this purchase order (Invoice #{$existingInvoice->id}).",
                errorCode: 'AP_INVOICE_ALREADY_EXISTS',
                httpStatus: 422,
            );
        }

        // Verify vendor is active
        $vendor = $po->vendor;
        if ($vendor === null) {
            throw new DomainException(
                message: 'Purchase order has no associated vendor.',
                errorCode: 'AP_PO_NO_VENDOR',
                httpStatus: 422,
            );
        }

        $vendor->assertActive();

        // Get open fiscal period
        /** @var FiscalPeriod|null $fiscalPeriod */
        $fiscalPeriod = FiscalPeriod::open()->latest('date_from')->first();

        if ($fiscalPeriod === null) {
            throw new DomainException(
                message: 'No open fiscal period found. Please open a fiscal period first.',
                errorCode: 'AP_NO_OPEN_FISCAL_PERIOD',
                httpStatus: 422,
            );
        }

        // Parse payment terms for due date calculation
        $paymentDays = 30;
        if (filled($vendor->payment_terms)) {
            preg_match('/\d+/', (string) $vendor->payment_terms, $matches);
            if (! empty($matches)) {
                $paymentDays = (int) $matches[0];
            }
        }

        $invoiceDate = now()->toDateString();
        $dueDate = now()->addDays($paymentDays)->toDateString();

        // Calculate net amount from PO items (quantity_received or quantity_ordered × unit_cost)
        // For partial receipts, we use what's actually been received
        $netAmount = $po->items->reduce(
            fn (float $carry, PurchaseOrderItem $item): float => $carry + ((float) $item->quantity_received * (float) $item->agreed_unit_cost),
            0.0,
        );

        // If nothing received yet, use ordered quantity (pre-payment scenario)
        if ($netAmount <= 0) {
            $netAmount = $po->items->reduce(
                fn (float $carry, PurchaseOrderItem $item): float => $carry + ((float) $item->quantity_ordered * (float) $item->agreed_unit_cost),
                0.0,
            );
        }

        // Resolve default GL accounts
        $apAccount = ChartOfAccount::where('code', '2001')->first();
        $expenseAccount = ChartOfAccount::where('code', '6001')->first();

        // Compute EWT preview
        $ewtAmount = $vendor->is_ewt_subject
            ? $this->ewtService->computeForInvoice(
                vendor: $vendor,
                netAmount: $netAmount,
                invoiceDate: Carbon::parse($invoiceDate),
            )
            : 0.00;

        // Create the draft invoice
        $invoice = VendorInvoice::create([
            'vendor_id' => $vendor->id,
            'fiscal_period_id' => $fiscalPeriod->id,
            'ap_account_id' => $apAccount?->id,
            'expense_account_id' => $expenseAccount?->id,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'net_amount' => $netAmount,
            'vat_amount' => 0.00,
            'ewt_amount' => $ewtAmount,
            'ewt_rate' => $vendor->is_ewt_subject ? $vendor->ewtRate?->rate : null,
            'atc_code' => $vendor->is_ewt_subject ? $vendor->atc_code : null,
            'description' => "From PO {$po->po_reference}",
            'purchase_order_id' => $po->id,
            'status' => 'draft',
            'created_by' => $userId,
        ]);

        // Format PO items for the frontend (to pre-populate invoice lines)
        $poItems = $po->items->map(fn ($item) => [
            'po_item_id' => $item->id,
            'description' => $item->item_description,
            'quantity_ordered' => (float) $item->quantity_ordered,
            'quantity_received' => (float) $item->quantity_received,
            'quantity_pending' => (float) $item->quantity_pending,
            'unit_of_measure' => $item->unit_of_measure,
            'unit_cost' => (float) $item->agreed_unit_cost,
            'total_cost' => (float) $item->total_cost,
        ])->toArray();

        return [
            'invoice' => $invoice,
            'po_items' => $poItems,
            'po_reference' => $po->po_reference,
            'vendor_name' => $vendor->name,
        ];
    }
}
