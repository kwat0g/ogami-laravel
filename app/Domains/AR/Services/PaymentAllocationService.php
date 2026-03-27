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
 * Supports:
 * - Manual allocation (user picks specific invoices)
 * - FIFO auto-allocation (oldest invoices first)
 * - Excess payment → advance deposit
 * - Advance payment application against new invoices
 */
final class PaymentAllocationService implements ServiceContract
{
    /**
     * Auto-allocate a payment to open invoices using FIFO (oldest due date first).
     *
     * If the payment exceeds all open invoices, the remainder goes to customer advance.
     *
     * @return array{allocated: Collection, advance_created: bool, advance_amount: float}
     */
    public function autoAllocate(CustomerPayment $payment): array
    {
        if ($payment->is_allocated) {
            throw new DomainException(
                'Payment has already been fully allocated.',
                'AR_PAYMENT_ALREADY_ALLOCATED',
                422,
            );
        }

        $remaining = (float) $payment->amount - (float) ($payment->allocated_amount ?? 0);
        if ($remaining <= 0) {
            throw new DomainException(
                'No remaining amount to allocate.',
                'AR_PAYMENT_NO_REMAINING',
                422,
            );
        }

        $openInvoices = CustomerInvoice::query()
            ->where('customer_id', $payment->customer_id)
            ->whereIn('status', ['approved', 'partially_paid'])
            ->orderBy('due_date')
            ->get();

        return DB::transaction(function () use ($payment, $openInvoices, $remaining): array {
            $allocated = collect();

            foreach ($openInvoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $balanceDue = (float) $invoice->balance_due;
                if ($balanceDue <= 0) {
                    continue;
                }

                $allocationAmount = min($remaining, $balanceDue);
                $remaining -= $allocationAmount;

                // Record allocation (via payment linkage)
                $this->applyToInvoice($invoice, $allocationAmount);

                $allocated->push([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount_applied' => round($allocationAmount, 2),
                    'invoice_balance_after' => round($balanceDue - $allocationAmount, 2),
                ]);
            }

            // Update payment allocated amount
            $totalAllocated = (float) ($payment->allocated_amount ?? 0) + ($payment->amount - $remaining - ($payment->allocated_amount ?? 0));
            $payment->update([
                'allocated_amount' => $payment->amount - $remaining,
                'is_allocated' => $remaining <= 0.005, // float tolerance
            ]);

            // If excess, create advance deposit
            $advanceCreated = false;
            $advanceAmount = 0.0;
            if ($remaining > 0.005) {
                CustomerAdvancePayment::create([
                    'customer_id' => $payment->customer_id,
                    'amount' => round($remaining, 2),
                    'source_payment_id' => $payment->id,
                    'status' => 'unapplied',
                    'notes' => "Auto-generated from excess payment #{$payment->id}",
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
     * Manually allocate a payment to specific invoices.
     *
     * @param array<int, array{invoice_id: int, amount: float}> $allocations
     * @return Collection<int, array{invoice_id: int, amount_applied: float}>
     */
    public function manualAllocate(CustomerPayment $payment, array $allocations): Collection
    {
        $totalToAllocate = array_sum(array_column($allocations, 'amount'));
        $remaining = (float) $payment->amount - (float) ($payment->allocated_amount ?? 0);

        if ($totalToAllocate > $remaining + 0.005) {
            throw new DomainException(
                sprintf('Allocation total (%.2f) exceeds remaining payment amount (%.2f).', $totalToAllocate, $remaining),
                'AR_ALLOCATION_EXCEEDS_PAYMENT',
                422,
            );
        }

        return DB::transaction(function () use ($payment, $allocations, $totalToAllocate): Collection {
            $result = collect();

            foreach ($allocations as $alloc) {
                $invoice = CustomerInvoice::findOrFail($alloc['invoice_id']);

                if ($invoice->customer_id !== $payment->customer_id) {
                    throw new DomainException(
                        'Invoice does not belong to the same customer as the payment.',
                        'AR_ALLOCATION_CUSTOMER_MISMATCH',
                        422,
                    );
                }

                $amount = (float) $alloc['amount'];
                if ($amount > $invoice->balance_due + 0.005) {
                    throw new DomainException(
                        sprintf('Allocation %.2f exceeds invoice balance %.2f.', $amount, $invoice->balance_due),
                        'AR_ALLOCATION_EXCEEDS_BALANCE',
                        422,
                    );
                }

                $this->applyToInvoice($invoice, $amount);

                $result->push([
                    'invoice_id' => $invoice->id,
                    'amount_applied' => round($amount, 2),
                ]);
            }

            $payment->update([
                'allocated_amount' => ($payment->allocated_amount ?? 0) + $totalToAllocate,
                'is_allocated' => ($payment->allocated_amount ?? 0) + $totalToAllocate >= $payment->amount - 0.005,
            ]);

            return $result;
        });
    }

    /**
     * Apply advance payment to an invoice.
     */
    public function applyAdvance(CustomerAdvancePayment $advance, CustomerInvoice $invoice, float $amount): void
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

        DB::transaction(function () use ($advance, $invoice, $amount): void {
            $this->applyToInvoice($invoice, $amount);

            $remainingAdvance = (float) $advance->amount - $amount;
            if ($remainingAdvance <= 0.005) {
                $advance->update(['status' => 'applied', 'applied_to_invoice_id' => $invoice->id]);
            } else {
                $advance->update(['amount' => round($remainingAdvance, 2)]);
            }
        });
    }

    /**
     * Get unallocated payments for a customer.
     *
     * @return Collection<int, CustomerPayment>
     */
    public function unallocatedPayments(Customer $customer): Collection
    {
        return CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where(function ($q) {
                $q->where('is_allocated', false)
                    ->orWhereNull('is_allocated');
            })
            ->orderBy('payment_date')
            ->get();
    }

    // ── Internal ──────────────────────────────────────────────────────────

    private function applyToInvoice(CustomerInvoice $invoice, float $amount): void
    {
        $newBalance = (float) $invoice->balance_due - $amount;

        if ($newBalance <= 0.005) {
            $invoice->update(['status' => 'paid']);
        } else {
            $invoice->update(['status' => 'partially_paid']);
        }
    }
}
