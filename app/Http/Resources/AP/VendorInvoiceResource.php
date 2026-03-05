<?php

declare(strict_types=1);

namespace App\Http\Resources\AP;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\AP\Models\VendorInvoice
 */
final class VendorInvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Domains\AP\Models\VendorInvoice $inv */
        $inv = $this->resource;

        return [
            'id' => $inv->id,
            'ulid' => $inv->ulid,
            'vendor_id' => $inv->vendor_id,
            'fiscal_period_id' => $inv->fiscal_period_id,
            'ap_account_id' => $inv->ap_account_id,
            'expense_account_id' => $inv->expense_account_id,
            'invoice_date' => $inv->invoice_date->toDateString(),
            'due_date' => $inv->due_date->toDateString(),
            'net_amount' => (float) $inv->net_amount,
            'vat_amount' => (float) $inv->vat_amount,
            'ewt_amount' => (float) $inv->ewt_amount,
            // AP-005: computed, never stored
            'net_payable' => $inv->net_payable,
            'total_paid' => $inv->total_paid,
            'balance_due' => $inv->balance_due,
            'or_number' => $inv->or_number,
            'vat_exemption_reason' => $inv->vat_exemption_reason,
            'atc_code' => $inv->atc_code,
            'ewt_rate' => $inv->ewt_rate !== null ? (float) $inv->ewt_rate : null,
            'status' => $inv->status,
            'is_overdue' => $inv->is_overdue,
            'rejection_note' => $inv->rejection_note,
            'description' => $inv->description,
            'journal_entry_id' => $inv->journal_entry_id,
            'created_by' => $inv->created_by,
            'submitted_by' => $inv->submitted_by,
            'approved_by' => $inv->approved_by,
            'submitted_at' => $inv->submitted_at?->toIso8601String(),
            'approved_at' => $inv->approved_at?->toIso8601String(),
            'created_at' => $inv->created_at->toIso8601String(),
            'updated_at' => $inv->updated_at->toIso8601String(),
            // Eager-loaded relations
            'vendor' => $this->whenLoaded('vendor', fn () => new VendorResource($inv->vendor)),
            'payments' => $this->whenLoaded('payments', fn () => $inv->payments->map(fn ($p) => [
                'id' => $p->id,
                'payment_date' => $p->payment_date->toDateString(),
                'amount' => (float) $p->amount,
                'reference_number' => $p->reference_number,
                'payment_method' => $p->payment_method,
                'form_2307_generated' => $p->form_2307_generated,
                'form_2307_generated_at' => $p->form_2307_generated_at?->toIso8601String(),
                'created_at' => $p->created_at->toIso8601String(),
            ])),
        ];
    }
}
