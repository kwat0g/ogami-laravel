<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\AP\Models\VendorInvoice;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Early Payment Discount Service — Item 24.
 *
 * Parses payment terms like "2/10 Net 30" and calculates discount
 * available if invoice is paid within the discount window.
 *
 * Format: "{discount_pct}/{discount_days} Net {due_days}"
 *   Example: "2/10 Net 30" = 2% discount if paid within 10 days, due in 30 days.
 *
 * Also provides a payment optimization report: which invoices to pay
 * this week to capture maximum discount savings.
 */
final class EarlyPaymentDiscountService implements ServiceContract
{
    /**
     * Parse payment terms string into structured data.
     *
     * @return array{discount_pct: float, discount_days: int, net_days: int, has_discount: bool}
     */
    public function parseTerms(?string $terms): array
    {
        if (empty($terms)) {
            return ['discount_pct' => 0, 'discount_days' => 0, 'net_days' => 30, 'has_discount' => false];
        }

        // Match patterns like "2/10 Net 30", "1.5/15 Net 45", etc.
        if (preg_match('/(\d+\.?\d*)\s*\/\s*(\d+)\s*[Nn]et\s*(\d+)/', $terms, $matches)) {
            return [
                'discount_pct' => (float) $matches[1],
                'discount_days' => (int) $matches[2],
                'net_days' => (int) $matches[3],
                'has_discount' => true,
            ];
        }

        // Match simple "Net 30", "Net 60" etc.
        if (preg_match('/[Nn]et\s*(\d+)/', $terms, $matches)) {
            return [
                'discount_pct' => 0,
                'discount_days' => 0,
                'net_days' => (int) $matches[1],
                'has_discount' => false,
            ];
        }

        return ['discount_pct' => 0, 'discount_days' => 0, 'net_days' => 30, 'has_discount' => false];
    }

    /**
     * Calculate discount available for a specific invoice.
     *
     * @return array{invoice_id: int, vendor_name: string, invoice_amount_centavos: int, discount_available: bool, discount_amount_centavos: int, discount_deadline: string|null, days_remaining: int, net_payable_centavos: int}
     */
    public function calculateDiscount(VendorInvoice $invoice): array
    {
        $invoice->loadMissing('vendor');
        $terms = $this->parseTerms($invoice->vendor?->payment_terms);

        $invoiceDate = $invoice->invoice_date ?? $invoice->created_at?->toDateString();
        $invoiceAmount = (int) (($invoice->net_amount ?? 0) * 100); // Convert to centavos if needed

        $discountAvailable = false;
        $discountAmount = 0;
        $discountDeadline = null;
        $daysRemaining = 0;

        if ($terms['has_discount'] && $invoiceDate) {
            $deadline = now()->parse($invoiceDate)->addDays($terms['discount_days']);
            $discountDeadline = $deadline->toDateString();
            $daysRemaining = max(0, (int) now()->diffInDays($deadline, false));
            $discountAvailable = $daysRemaining > 0;
            $discountAmount = $discountAvailable
                ? (int) round($invoiceAmount * $terms['discount_pct'] / 100)
                : 0;
        }

        return [
            'invoice_id' => $invoice->id,
            'vendor_name' => $invoice->vendor?->name ?? '—',
            'invoice_amount_centavos' => $invoiceAmount,
            'discount_available' => $discountAvailable,
            'discount_pct' => $terms['discount_pct'],
            'discount_amount_centavos' => $discountAmount,
            'discount_deadline' => $discountDeadline,
            'days_remaining' => $daysRemaining,
            'net_payable_centavos' => $invoiceAmount - $discountAmount,
        ];
    }

    /**
     * Payment optimization: invoices to pay this week to capture discounts.
     *
     * Returns invoices ordered by discount savings (highest first),
     * filtered to those with discount deadlines within the next 7 days.
     *
     * @return Collection<int, array>
     */
    public function paymentOptimization(int $lookAheadDays = 7): Collection
    {
        $cutoff = now()->addDays($lookAheadDays)->toDateString();

        $invoices = VendorInvoice::query()
            ->whereIn('status', ['approved', 'partially_paid'])
            ->whereNull('deleted_at')
            ->with('vendor')
            ->get();

        $opportunities = collect();

        foreach ($invoices as $invoice) {
            $result = $this->calculateDiscount($invoice);

            if ($result['discount_available'] && $result['discount_deadline'] <= $cutoff) {
                $opportunities->push($result);
            }
        }

        return $opportunities->sortByDesc('discount_amount_centavos')->values();
    }

    /**
     * Summary: total potential savings if all eligible discounts are captured.
     *
     * @return array{total_invoices: int, eligible_for_discount: int, total_savings_centavos: int, total_payable_with_discount_centavos: int}
     */
    public function discountSummary(): array
    {
        $invoices = VendorInvoice::query()
            ->whereIn('status', ['approved', 'partially_paid'])
            ->whereNull('deleted_at')
            ->with('vendor')
            ->get();

        $eligible = 0;
        $totalSavings = 0;
        $totalPayable = 0;

        foreach ($invoices as $invoice) {
            $result = $this->calculateDiscount($invoice);
            if ($result['discount_available']) {
                $eligible++;
                $totalSavings += $result['discount_amount_centavos'];
            }
            $totalPayable += $result['net_payable_centavos'];
        }

        return [
            'total_invoices' => $invoices->count(),
            'eligible_for_discount' => $eligible,
            'total_savings_centavos' => $totalSavings,
            'total_payable_with_discount_centavos' => $totalPayable,
        ];
    }
}
