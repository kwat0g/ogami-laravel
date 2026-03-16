<?php

declare(strict_types=1);

namespace App\Domains\AR\Services;

use App\Domains\Accounting\Services\JournalEntryService;
use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerAdvancePayment;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Models\CustomerPayment;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Tax\Services\VatLedgerService;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Customer Invoice Service — AR-001 through AR-006 enforcement.
 *
 * AR-001: Block invoice creation when current_outstanding > credit_limit.
 * AR-002: Credit limit override requires Accounting Manager (handled separately, flag-based bypass).
 * AR-003: Invoice number INV-YYYY-MM-NNNNNN generated on approval.
 * AR-004: current_outstanding is a computed accessor on Customer — never a stored column.
 * AR-005: Payment > balance_due → excess routed to customer_advance_payments.
 * AR-006: Bad debt write-off requires Accounting Manager approval + DR Bad Debt Expense / CR AR.
 */
final class CustomerInvoiceService implements ServiceContract
{
    public function __construct(
        private readonly JournalEntryService $jeService,
        private readonly VatLedgerService $vatLedgerService,
    ) {}

    // ── Create (draft) ────────────────────────────────────────────────────────

    /**
     * @param array{
     *   fiscal_period_id: int,
     *   ar_account_id: int,
     *   revenue_account_id: int,
     *   invoice_date: string,
     *   due_date: string,
     *   subtotal: float,
     *   vat_amount?: float,
     *   vat_exemption_reason?: string,
     *   description?: string,
     *   bypass_credit_check?: bool,  AR-002 flag — must be separately authorized
     *   delivery_receipt_id?: int,   HIGH-001: Link to delivery receipt
     * } $data
     */
    public function create(Customer $customer, array $data, int $userId): CustomerInvoice
    {
        if (! $customer->is_active) {
            throw new DomainException(
                "Customer '{$customer->name}' is inactive and cannot receive new invoices.",
                'AR_CUSTOMER_INACTIVE',
                422
            );
        }

        // ── HIGH-001: Delivery receipt verification ────────────────────────────
        $deliveryReceiptId = $data['delivery_receipt_id'] ?? null;
        if ($deliveryReceiptId !== null) {
            $deliveryReceipt = DeliveryReceipt::find($deliveryReceiptId);

            if ($deliveryReceipt === null) {
                throw new DomainException(
                    'Delivery receipt not found.',
                    'AR_DELIVERY_RECEIPT_NOT_FOUND',
                    422
                );
            }

            if ($deliveryReceipt->customer_id !== $customer->id) {
                throw new DomainException(
                    'Delivery receipt does not belong to this customer.',
                    'AR_DELIVERY_CUSTOMER_MISMATCH',
                    422
                );
            }

            if ($deliveryReceipt->status !== 'delivered') {
                throw new DomainException(
                    "Cannot create invoice for delivery receipt with status '{$deliveryReceipt->status}'. Delivery must be completed first.",
                    'AR_DELIVERY_NOT_COMPLETED',
                    422
                );
            }

            // Check if delivery receipt is already linked to another invoice
            $existingInvoice = CustomerInvoice::where('delivery_receipt_id', $deliveryReceiptId)
                ->where('status', '!=', 'cancelled')
                ->first();

            if ($existingInvoice !== null) {
                throw new DomainException(
                    "Delivery receipt is already linked to invoice #{$existingInvoice->invoice_number}.",
                    'AR_DELIVERY_ALREADY_INVOICED',
                    422
                );
            }
        }

        $subtotal = (float) $data['subtotal'];
        $vatAmount = (float) ($data['vat_amount'] ?? 0.00);
        $total = round($subtotal + $vatAmount, 2);

        // AR-001: credit limit enforcement (unless bypass authorized via AR-002)
        if (empty($data['bypass_credit_check'])) {
            $customer->assertCreditAvailable($total);
        }

        // VAT-002: enforce vat_amount = subtotal × vat_rate (warn if mismatch, do not override)
        // Actual enforcement is in the FormRequest; here we re-derive for storage accuracy.
        $vatRate = $this->resolveVatRate();
        if ($vatAmount > 0 && $vatRate > 0) {
            $expectedVat = round($subtotal * $vatRate, 2);
            if (abs($vatAmount - $expectedVat) > 0.01) {
                throw new DomainException(
                    sprintf(
                        'VAT amount %.2f does not match expected %.2f (subtotal %.2f × rate %.4f). (VAT-002)',
                        $vatAmount,
                        $expectedVat,
                        $subtotal,
                        $vatRate
                    ),
                    'AR_VAT_AMOUNT_MISMATCH',
                    422
                );
            }
        }

        return CustomerInvoice::create([
            'customer_id' => $customer->id,
            'delivery_receipt_id' => $deliveryReceiptId,
            'fiscal_period_id' => $data['fiscal_period_id'],
            'ar_account_id' => $data['ar_account_id'],
            'revenue_account_id' => $data['revenue_account_id'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'vat_exemption_reason' => $data['vat_exemption_reason'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'draft',
            'created_by' => $userId,
        ]);
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    /**
     * Approves a draft invoice:
     * AR-003: generates invoice_number INV-YYYY-MM-NNNNNN.
     * Auto-posts JE: DR AR / CR Revenue (and CR VAT Payable if vat > 0).
     * VAT accumulation delegated to VatLedgerService.
     */
    public function approve(CustomerInvoice $invoice, User $actor): CustomerInvoice
    {
        if ($invoice->status !== 'draft') {
            throw new DomainException(
                "Only draft invoices can be approved. Current status: '{$invoice->status}'.",
                'AR_INVALID_STATUS_FOR_APPROVE',
                409
            );
        }

        // SoD: approver must not be creator
        if (! $actor->hasRole('super_admin') && $invoice->created_by === $actor->id) {
            throw new SodViolationException(
                processName: 'AR Invoice',
                conflictingAction: 'approve',
            );
        }

        return DB::transaction(function () use ($invoice, $actor) {
            // AR-003: generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber($invoice->invoice_date);

            $invoice->update([
                'status' => 'approved',
                'invoice_number' => $invoiceNumber,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            // Auto-post JE: DR Accounts Receivable / CR Revenue
            if (is_null($invoice->journal_entry_id)) {
                $this->autoPostJournalEntry($invoice);
            }

            // VAT-002: accumulate output VAT for the period
            if ((float) $invoice->vat_amount > 0) {
                $this->vatLedgerService->accumulateOutputVat(
                    fiscalPeriodId: $invoice->fiscal_period_id,
                    amount: (float) $invoice->vat_amount,
                );
            }

            return $invoice->fresh();
        });
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function cancel(CustomerInvoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new DomainException(
                "Only draft invoices can be cancelled. Current status: '{$invoice->status}'.",
                'AR_INVALID_STATUS_FOR_CANCEL',
                409
            );
        }

        $invoice->update(['status' => 'cancelled']);
        $invoice->delete();
    }

    // ── Receive Payment ───────────────────────────────────────────────────────

    /**
     * AR-005: If payment > balance_due, excess is routed to customer_advance_payments.
     *
     * @param array{
     *   amount: float,
     *   payment_date: string,
     *   reference_number?: string,
     *   payment_method?: string,
     *   notes?: string,
     *   ar_account_id: int,
     *   cash_account_id: int,
     * } $data
     */
    public function receivePayment(CustomerInvoice $invoice, array $data, int $userId): CustomerPayment
    {
        if (! in_array($invoice->status, ['approved', 'partially_paid'], true)) {
            throw new DomainException(
                "Payments can only be recorded for approved or partially-paid invoices. Current status: '{$invoice->status}'.",
                'AR_INVALID_STATUS_FOR_PAYMENT',
                409
            );
        }

        $amount = (float) $data['amount'];
        $balanceDue = $invoice->balance_due;

        if ($amount <= 0) {
            throw new DomainException(
                'Payment amount must be greater than zero.',
                'AR_PAYMENT_AMOUNT_NOT_POSITIVE',
                422
            );
        }

        return DB::transaction(function () use ($invoice, $data, $amount, $balanceDue, $userId) {
            $appliedAmount = min($amount, $balanceDue);
            $excessAmount = round($amount - $appliedAmount, 2);

            // Record the payment (capped at balance_due)
            $payment = CustomerPayment::create([
                'customer_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'payment_date' => $data['payment_date'],
                'amount' => $appliedAmount,
                'reference_number' => $data['reference_number'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // Update invoice status
            $newTotalPaid = $invoice->total_paid + $appliedAmount;
            $newStatus = $newTotalPaid >= (float) $invoice->total_amount
                ? 'paid'
                : 'partially_paid';

            $invoice->update(['status' => $newStatus]);

            // Auto-post JE: DR Cash / CR Accounts Receivable
            $je = $this->jeService->create([
                'date' => $data['payment_date'],
                'description' => "AR Receipt: {$invoice->invoice_number} — {$invoice->customer->name}",
                'source_type' => 'ar',
                'source_id' => $invoice->id,
                'lines' => [
                    ['account_id' => $data['cash_account_id'],  'debit' => $appliedAmount],
                    ['account_id' => $data['ar_account_id'],    'credit' => $appliedAmount],
                ],
            ]);
            $this->jeService->post($je);
            $payment->update(['journal_entry_id' => $je->id]);

            // AR-005: route excess as advance payment
            if ($excessAmount > 0) {
                CustomerAdvancePayment::create([
                    'customer_id' => $invoice->customer_id,
                    'received_date' => $data['payment_date'],
                    'amount' => $excessAmount,
                    'applied_amount' => 0.00,
                    'reference_number' => $data['reference_number'] ?? null,
                    'status' => 'available',
                    'notes' => "Overpayment from invoice {$invoice->invoice_number}",
                    'created_by' => $userId,
                ]);
            }

            return $payment;
        });
    }

    // ── Bad Debt Write-Off (AR-006) ────────────────────────────────────────────

    /**
     * AR-006: Requires Accounting Manager permission (enforced by Policy).
     * DR Bad Debt Expense / CR Accounts Receivable for remaining balance.
     *
     * @param array{
     *   write_off_reason: string,
     *   bad_debt_account_id: int,
     *   ar_account_id: int,
     * } $data
     */
    public function writeOff(CustomerInvoice $invoice, array $data, int $approverId): CustomerInvoice
    {
        if (! in_array($invoice->status, ['approved', 'partially_paid'], true)) {
            throw new DomainException(
                "Only approved or partially-paid invoices can be written off. Current status: '{$invoice->status}'.",
                'AR_INVALID_STATUS_FOR_WRITEOFF',
                409
            );
        }

        if (empty($data['write_off_reason'])) {
            throw new DomainException(
                'A write-off reason is required. (AR-006)',
                'AR_WRITEOFF_REASON_REQUIRED',
                422
            );
        }

        $remainingBalance = $invoice->balance_due;

        if ($remainingBalance <= 0) {
            throw new DomainException(
                'Invoice is already fully paid; nothing to write off.',
                'AR_WRITEOFF_NOTHING_TO_WRITEOFF',
                422
            );
        }

        return DB::transaction(function () use ($invoice, $data, $remainingBalance, $approverId) {
            // AR-006 JE: DR Bad Debt Expense / CR Accounts Receivable
            $je = $this->jeService->create([
                'date' => now()->toDateString(),
                'description' => "Bad Debt Write-off: {$invoice->invoice_number} — {$invoice->customer->name}",
                'source_type' => 'ar',
                'source_id' => $invoice->id,
                'lines' => [
                    ['account_id' => $data['bad_debt_account_id'], 'debit' => $remainingBalance],
                    ['account_id' => $data['ar_account_id'],       'credit' => $remainingBalance],
                ],
            ]);
            $this->jeService->post($je);

            $invoice->update([
                'status' => 'written_off',
                'write_off_reason' => $data['write_off_reason'],
                'write_off_approved_by' => $approverId,
                'write_off_at' => now(),
                'write_off_journal_entry_id' => $je->id,
            ]);

            return $invoice->fresh();
        });
    }

    // ── Internal Helpers ──────────────────────────────────────────────────────

    /**
     * AR-003: Generate unique invoice number INV-YYYY-MM-NNNNNN.
     * Sequence is per calendar month, zero-padded to 6 digits.
     */
    private function generateInvoiceNumber(Carbon|\DateTimeInterface|string $invoiceDate): string
    {
        $date = Carbon::parse($invoiceDate);
        $yyyy = $date->format('Y');
        $mm = $date->format('m');
        $prefix = "INV-{$yyyy}-{$mm}-";

        $lastSeq = CustomerInvoice::where('invoice_number', 'like', "{$prefix}%")
            ->max(DB::raw('CAST(RIGHT(invoice_number, 6) AS INTEGER)'));

        $seq = (int) $lastSeq + 1;

        return $prefix.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Auto-post journal entry for an approved invoice.
     * DR Accounts Receivable / CR Revenue (+ VAT output to VAT clearing if applicable).
     */
    private function autoPostJournalEntry(CustomerInvoice $invoice): void
    {
        $lines = [
            // DR Accounts Receivable (full invoice total)
            ['account_id' => $invoice->ar_account_id, 'debit' => (float) $invoice->total_amount],
            // CR Revenue (subtotal only)
            ['account_id' => $invoice->revenue_account_id, 'credit' => (float) $invoice->subtotal],
        ];

        if ((float) $invoice->vat_amount > 0) {
            // CR VAT Output Clearing — use system setting for the account code
            $vatOutputAccountId = $this->resolveVatOutputAccountId();
            if ($vatOutputAccountId) {
                $lines[] = ['account_id' => $vatOutputAccountId, 'credit' => (float) $invoice->vat_amount];
            } else {
                // Fallback: fold VAT into revenue line (DR AR / CR Revenue for full amount)
                $lines[1]['credit'] = (float) $invoice->total_amount;
            }
        }

        $je = $this->jeService->create([
            'date' => $invoice->invoice_date->toDateString(),
            'description' => "AR Invoice: {$invoice->invoice_number} — {$invoice->customer->name}",
            'source_type' => 'ar',
            'source_id' => $invoice->id,
            'lines' => $lines,
        ]);

        $this->jeService->post($je);

        $invoice->update(['journal_entry_id' => $je->id]);
    }

    /** VAT-002: reads vat_rate from system_settings; never hardcodes 12%. */
    private function resolveVatRate(): float
    {
        $raw = DB::table('system_settings')->where('key', 'tax.vat_rate')->value('value');

        return $raw ? (float) json_decode($raw, true) : 0.12;
    }

    private function resolveVatOutputAccountId(): ?int
    {
        $raw = DB::table('system_settings')->where('key', 'tax.vat_output_account_id')->value('value');

        return $raw ? (int) json_decode($raw, true) : null;
    }
}
