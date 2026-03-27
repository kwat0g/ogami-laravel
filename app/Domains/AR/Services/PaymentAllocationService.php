<?php

declare(strict_types=1);

namespace App\Domains\AR\Services;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerAdvancePayment;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Models\CustomerPayment;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Payment Allocation Service — allocates customer payments to open invoices.
 *
 * Works with the existing CustomerPayment model which links to a single invoice
 * via customer_invoice_id. For split-payment scenarios, multiple CustomerPayment
 * records are created from the same receipt.
 *
 * Supports:
 * - FIFO auto-allocation (oldest invoices first)
 * - Manual allocation to specific invoices
 * - Excess payment → advance deposit
 * - Advance payment application against invoices
 */
final class PaymentAllocationService implements ServiceContract
{
    /**
     * Auto-allocate a payment amount to open invoices using FIFO (oldest due date first).
     *
     * Creates individual CustomerPayment records per invoice and routes any
     * excess to a CustomerAdvancePayment.
     *
     * @param array{
     *     customer_id: int,
     *     amount: float,
     *     payment_date: string,
     *     payment_method?: string,
     *     reference_number?: string,
     *     notes?: string,
     * } $data
     * @param int $userId
     *
     * @return array{allocated: Collection, advance_created: bool, advance_amount: float}
     */
    public function autoAllocate(array $data, int $userId): array
    {
        $customerId = $data['customer_id'];
        $totalAmount = (float) $data['amount'];

        $openInvoices = CustomerInvoice::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', ['approved', 'partially_paid'])
            ->orderBy('due_date')
            ->get();

        return DB::transaction(function () use ($data, $openInvoices, $totalAmount, $userId): array {
            $remaining = $totalAmount;
            $allocated = collect();

            foreach ($openInvoices as $invoice) {
                if ($remaining <= 0.005) {
                    break;
                }

                $balanceDue = (float) $invoice->balance_due;
                if ($balanceDue <= 0) {
                    continue;
                }

                $allocationAmount = min($remaining, $balanceDue);
                $remaining -= $allocationAmount;

                // Create a payment record for this invoice
                CustomerPayment::create([
                    'customer_invoice_id' => $invoice->id,
                    'customer_id' => $data['customer_id'],
                    'payment_date' => $data['payment_date'],
                    'amount' => round($allocationAmount, 2),
                    'payment_method' => $data['payment_method'] ?? null,
                    'reference_number' => $data['reference_number'] ?? null,
                    'notes' => $data['notes'] ?? "Auto-allocated to {$invoice->invoice_number}",
                    'created_by' => $userId,
                ]);

                // Update invoice status
                $newBalance = $balanceDue - $allocationAmount;
                $invoice->update([
                    'status' => $newBalance <= 0.005 ? 'paid' : 'partially_paid',
                ]);

                $allocated->push([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount_applied' => round($allocationAmount, 2),
                    'invoice_balance_after' => round(max(0, $newBalance), 2),
                ]);
            }

            // Route excess to advance payment
            $advanceCreated = false;
            $advanceAmount = 0.0;
            if ($remaining > 0.005) {
                CustomerAdvancePayment::create([
                    'customer_id' => $data['customer_id'],
                    'amount' => round($remaining, 2),
                    'status' => 'unapplied',
                    'notes' => "Excess from payment ref: " . ($data['reference_number'] ?? 'N/A'),
                ]);
                $advanceCreated = true;
                $advanceAmount = round($remaining, 2);
            }

            return [
                'allocated' => $allocated,
                'advance_created' => $advanceCreated,
                'advance_amount' => $advanceAmount,
            ];
        });
    }

    /**
     * Manually allocate to specific invoices.
     *
     * @param array{
     *     customer_id: int,
     *     payment_date: string,
     *     payment_method?: string,
     *     reference_number?: string,
     *     notes?: string,
     * } $paymentData
     * @param array<int, array{invoice_id: int, amount: float}> $allocations
     * @param int $userId
     *
     * @return Collection<int, array{invoice_id: int, amount_applied: float}>
     */
    public function manualAllocate(array $paymentData, array $allocations, int $userId): Collection
    {
        return DB::transaction(function () use ($paymentData, $allocations, $userId): Collection {
            $result = collect();

            foreach ($allocations as $alloc) {
                $invoice = CustomerInvoice::findOrFail($alloc['invoice_id']);

                if ($invoice->customer_id !== $paymentData['customer_id']) {
                    throw new DomainException(
                        'Invoice does not belong to the specified customer.',
                        'AR_ALLOCATION_CUSTOMER_MISMATCH',
                        422,
                    );
                }

                $amount = (float) $alloc['amount'];
                $balanceDue = (float) $invoice->balance_due;

                if ($amount > $balanceDue + 0.005) {
                    throw new DomainException(
                        sprintf('Allocation %.2f exceeds invoice balance %.2f.', $amount, $balanceDue),
                        'AR_ALLOCATION_EXCEEDS_BALANCE',
                        422,
                    );
                }

                CustomerPayment::create([
                    'customer_invoice_id' => $invoice->id,
                    'customer_id' => $paymentData['customer_id'],
                    'payment_date' => $paymentData['payment_date'],
                    'amount' => round($amount, 2),
                    'payment_method' => $paymentData['payment_method'] ?? null,
                    'reference_number' => $paymentData['reference_number'] ?? null,
                    'notes' => $paymentData['notes'] ?? null,
                    'created_by' => $userId,
                ]);

                $newBalance = $balanceDue - $amount;
                $invoice->update([
                    'status' => $newBalance <= 0.005 ? 'paid' : 'partially_paid',
                ]);

                $result->push([
                    'invoice_id' => $invoice->id,
                    'amount_applied' => round($amount, 2),
                ]);
            }

            return $result;
        });
    }

    /**
     * Apply an advance payment to an open invoice.
     */
    public function applyAdvance(CustomerAdvancePayment $advance, CustomerInvoice $invoice, float $amount, int $userId): void
    {
        if ($advance->status !== 'unapplied') {
            throw new DomainException(
                'Advance payment has already been applied.',
                'AR_ADVANCE_ALREADY_APPLIED',
                422,
            );
        }

        if ($amount > (float) $advance->amount + 0.005) {
            throw new DomainException(
                'Amount exceeds advance payment balance.',
                'AR_ADVANCE_EXCEEDS_BALANCE',
                422,
            );
        }

        DB::transaction(function () use ($advance, $invoice, $amount, $userId): void {
            // Create a payment record linked to the invoice
            CustomerPayment::create([
                'customer_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'payment_date' => now()->toDateString(),
                'amount' => round($amount, 2),
                'notes' => "Applied from advance payment #{$advance->id}",
                'created_by' => $userId,
            ]);

            $balanceDue = (float) $invoice->balance_due;
            $newBalance = $balanceDue - $amount;
            $invoice->update([
                'status' => $newBalance <= 0.005 ? 'paid' : 'partially_paid',
            ]);

            $remainingAdvance = (float) $advance->amount - $amount;
            if ($remainingAdvance <= 0.005) {
                $advance->update(['status' => 'applied']);
            } else {
                $advance->update(['amount' => round($remainingAdvance, 2)]);
            }
        });
    }

    /**
     * Get unapplied advance payments for a customer.
     *
     * @return Collection<int, CustomerAdvancePayment>
     */
    public function unappliedAdvances(Customer $customer): Collection
    {
        return CustomerAdvancePayment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'unapplied')
            ->orderBy('created_at')
            ->get();
    }
}
