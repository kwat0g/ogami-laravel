<?php

declare(strict_types=1);

namespace App\Http\Resources\AR;

use App\Domains\AR\Models\CustomerInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerInvoice
 */
final class CustomerInvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CustomerInvoice $inv */
        $inv = $this->resource;

        return [
            'id' => $inv->id,
            'ulid' => $inv->ulid,
            'invoice_number' => $inv->invoice_number,
            'status' => $inv->status,
            'customer_id' => $inv->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($inv->customer)),
            'fiscal_period_id' => $inv->fiscal_period_id,
            'fiscal_period' => $this->whenLoaded('fiscalPeriod', fn () => [
                'id' => $inv->fiscalPeriod?->id,
                'name' => $inv->fiscalPeriod?->name,
                'date_from' => $inv->fiscalPeriod?->date_from?->toDateString(),
                'date_to' => $inv->fiscalPeriod?->date_to?->toDateString(),
                'status' => $inv->fiscalPeriod?->status,
            ]),
            'ar_account_id' => $inv->ar_account_id,
            'revenue_account_id' => $inv->revenue_account_id,
            'invoice_date' => $inv->invoice_date->toDateString(),
            'due_date' => $inv->due_date->toDateString(),
            'subtotal' => (float) $inv->subtotal,
            'vat_amount' => (float) $inv->vat_amount,
            'total_amount' => (float) $inv->total_amount,
            'vat_exemption_reason' => $inv->vat_exemption_reason,
            'description' => $inv->description,
            // Computed payment info
            'total_paid' => $inv->total_paid,
            'balance_due' => $inv->balance_due,
            'is_overdue' => $inv->is_overdue,
            // Write-off (AR-006)
            'write_off_reason' => $inv->write_off_reason,
            'write_off_at' => $inv->write_off_at?->toIso8601String(),
            // Auth trail
            'created_by' => $inv->created_by,
            'approved_by' => $inv->approved_by,
            'approved_at' => $inv->approved_at?->toIso8601String(),
            // Relationships (lazy-loaded)
            'payments' => $this->whenLoaded(
                'payments',
                fn () => $inv->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'payment_date' => $p->payment_date->toDateString(),
                    'amount' => (float) $p->amount,
                    'reference_number' => $p->reference_number,
                    'payment_method' => $p->payment_method,
                    'notes' => $p->notes,
                    'created_by' => $p->created_by,
                    'created_at' => $p->created_at->toIso8601String(),
                ])
            ),
            'created_at' => $inv->created_at->toIso8601String(),
            'updated_at' => $inv->updated_at->toIso8601String(),
        ];
    }
}
