<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalEntryService;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Domains\AP\Models\VendorPayment;
use App\Domains\Procurement\Models\GoodsReceipt;
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
     * Approve a pending invoice and auto-post the corresponding JE.
     * AP-010: approver must not be the same person who submitted.
     */
    public function approve(VendorInvoice $invoice, int $approverId): VendorInvoice
    {
        if (! $invoice->isPendingApproval()) {
            throw new DomainException(
                message: "Only pending-approval invoices can be approved. Current status: '{$invoice->status}'.",
                errorCode: 'AP_INVALID_STATUS_FOR_APPROVE',
                httpStatus: 409,
            );
        }

        // AP-010 SoD: approver ≠ submitter
        if ($invoice->submitted_by === $approverId) {
            throw new SodViolationException(
                processName: 'AP Invoice',
                conflictingAction: 'approve',
            );
        }

        $invoice = DB::transaction(function () use ($invoice, $approverId) {
            $invoice->update([
                'status' => 'approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            // Auto-post JE only once (idempotency guard)
            if (is_null($invoice->journal_entry_id)) {
                $this->autoPostJournalEntry($invoice);
            }

            return $invoice->fresh();
        });

        // Notify the submitter their invoice was approved and posted to GL
        $this->notifySubmitterOfDecision($invoice, 'approved', null);

        return $invoice;
    }

    // ── Reject ────────────────────────────────────────────────────────────────

    public function reject(VendorInvoice $invoice, int $rejectorId, string $note): VendorInvoice
    {
        if (! $invoice->isPendingApproval()) {
            throw new DomainException(
                message: "Only pending-approval invoices can be rejected. Current status: '{$invoice->status}'.",
                errorCode: 'AP_INVALID_STATUS_FOR_REJECT',
                httpStatus: 409,
            );
        }

        if ($invoice->submitted_by === $rejectorId) {
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
                ->each(fn (User $u) => $u->notify(new VendorInvoiceSubmittedNotification($invoice)));
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
                new VendorInvoiceDecidedNotification($invoice, $decision, $rejectionNote)
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
    public function createFromPo(GoodsReceipt $gr, int|null $actorId): VendorInvoice
    {
        $po     = $gr->purchaseOrder()->with(['vendor', 'vendor.ewtRate'])->firstOrFail();
        $vendor = $po->vendor;

        if ($vendor === null) {
            throw new \RuntimeException('PO has no associated vendor — cannot auto-create AP invoice.');
        }

        /** @var FiscalPeriod|null $fiscalPeriod */
        $fiscalPeriod = FiscalPeriod::open()->latest('start_date')->first();

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
        $dueDate     = now()->addDays($paymentDays)->toDateString();
        $netAmount   = (float) $po->total_po_amount;

        $ewtAmount = $vendor->is_ewt_subject
            ? $this->ewtService->computeForInvoice(
                vendor: $vendor,
                netAmount: $netAmount,
                invoiceDate: Carbon::parse($invoiceDate),
            )
            : 0.00;

        return VendorInvoice::create([
            'vendor_id'          => $vendor->id,
            'fiscal_period_id'   => $fiscalPeriod->id,
            'ap_account_id'      => null,
            'expense_account_id' => null,
            'invoice_date'       => $invoiceDate,
            'due_date'           => $dueDate,
            'net_amount'         => $netAmount,
            'vat_amount'         => 0.00,
            'ewt_amount'         => $ewtAmount,
            'ewt_rate'           => $vendor->is_ewt_subject ? $vendor->ewtRate?->rate : null,
            'atc_code'           => $vendor->is_ewt_subject ? $vendor->atc_code : null,
            'description'        => "Auto-created from GR {$gr->gr_reference} / PO {$po->po_reference}",
            'source'             => 'auto_procurement',
            'purchase_order_id'  => $po->id,
            'status'             => 'draft',
            'created_by'         => $actorId ?? 1,
        ]);
    }
}
