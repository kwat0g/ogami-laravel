<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\AP\Models\PaymentBatch;
use App\Domains\AP\Models\PaymentBatchItem;
use App\Domains\AP\Models\VendorInvoice;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Payment Batch Service — manages batch vendor payment runs.
 *
 * Workflow: draft -> submitted -> approved -> processing -> completed
 */
final class PaymentBatchService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return PaymentBatch::with(['createdBy', 'approvedBy'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Create a payment batch from a list of vendor invoice IDs.
     *
     * @param array<string,mixed> $data
     */
    public function store(array $data, User $actor): PaymentBatch
    {
        return DB::transaction(function () use ($data, $actor): PaymentBatch {
            $batch = PaymentBatch::create([
                'batch_number' => $data['batch_number'] ?? 'PB-' . now()->format('Ymd-His'),
                'status' => 'draft',
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'notes' => $data['notes'] ?? null,
                'created_by_id' => $actor->id,
            ]);

            $totalAmount = 0;
            $count = 0;

            foreach ($data['invoice_ids'] ?? [] as $invoiceId) {
                $invoice = VendorInvoice::findOrFail($invoiceId);
                $balanceDue = (int) (($invoice->balance_due ?? $invoice->total_amount ?? 0) * 100);

                PaymentBatchItem::create([
                    'payment_batch_id' => $batch->id,
                    'vendor_invoice_id' => $invoice->id,
                    'vendor_id' => $invoice->vendor_id,
                    'amount_centavos' => $balanceDue,
                    'status' => 'pending',
                ]);

                $totalAmount += $balanceDue;
                $count++;
            }

            $batch->update([
                'total_amount_centavos' => $totalAmount,
                'payment_count' => $count,
            ]);

            return $batch->load('items.vendor', 'items.vendorInvoice');
        });
    }

    public function submit(PaymentBatch $batch): PaymentBatch
    {
        if ($batch->status !== 'draft') {
            throw new DomainException('Batch must be in draft to submit.', 'AP_INVALID_BATCH_STATUS', 422);
        }

        $batch->update(['status' => 'submitted']);

        return $batch->fresh() ?? $batch;
    }

    public function approve(PaymentBatch $batch, User $approver): PaymentBatch
    {
        if ($batch->status !== 'submitted') {
            throw new DomainException('Batch must be submitted to approve.', 'AP_INVALID_BATCH_STATUS', 422);
        }

        $batch->update([
            'status' => 'approved',
            'approved_by_id' => $approver->id,
            'approved_at' => now(),
        ]);

        return $batch->fresh() ?? $batch;
    }

    public function process(PaymentBatch $batch): PaymentBatch
    {
        if ($batch->status !== 'approved') {
            throw new DomainException('Batch must be approved to process.', 'AP_INVALID_BATCH_STATUS', 422);
        }

        return DB::transaction(function () use ($batch): PaymentBatch {
            $batch->update(['status' => 'processing']);

            // Mark all pending items as paid
            $batch->items()->where('status', 'pending')->update(['status' => 'paid']);

            $batch->update(['status' => 'completed']);

            return $batch->fresh('items') ?? $batch;
        });
    }
}
