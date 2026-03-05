<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\AP\Models\EwtRate;
use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorPayment;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * EWT Service — Expanded Withholding Tax computation and BIR Form 2307 helpers.
 *
 * AP-004: EWT = net_amount × ewt_rate (snapshot-based; rate frozen at invoice creation).
 * AP-009: Form 2307 aggregates all quarterly payments per vendor; each payment is
 *         flagged `form_2307_generated = true` idempotently.
 */
final class EwtService implements ServiceContract
{
    // ── Compute EWT for a single invoice (AP-004) ─────────────────────────────

    /**
     * Returns the EWT amount to withhold given vendor config and invoice date.
     * If the vendor is not EWT-subject or has no ATC code, returns 0.00.
     */
    public function computeForInvoice(Vendor $vendor, float $netAmount, Carbon $invoiceDate): float
    {
        if (! $vendor->is_ewt_subject || blank($vendor->atc_code)) {
            return 0.00;
        }

        $rate = EwtRate::query()
            ->scopeEffectiveOn($vendor->atc_code, $invoiceDate)
            ->where('is_active', true)
            ->first();

        if (! $rate) {
            return 0.00;
        }

        return round($netAmount * (float) $rate->rate, 2);
    }

    // ── Form 2307 (AP-009) ────────────────────────────────────────────────────

    /**
     * Collect all payments for a vendor in the given quarter and mark each as
     * `form_2307_generated = true` (idempotent — calling twice is safe).
     *
     * Returns structured data suitable for rendering the BIR Form 2307 PDF.
     */
    public function generateForm2307(Vendor $vendor, int $quarter, int $year): array
    {
        $payments = $this->quartersPayments($vendor, $quarter, $year);

        // Mark each payment as generated (idempotent)
        foreach ($payments as $payment) {
            if (! $payment->form_2307_generated) {
                $payment->update([
                    'form_2307_generated' => true,
                    'form_2307_generated_at' => now(),
                ]);
            }
        }

        $totalEwt = $payments->sum(fn (VendorPayment $p) => $this->ewtForPayment($p));
        $totalGross = $payments->sum('amount');

        return [
            'vendor' => $vendor,
            'quarter' => $quarter,
            'year' => $year,
            'payments' => $payments,
            'total_gross' => $totalGross,
            'total_ewt' => $totalEwt,
            'atc_code' => $vendor->atc_code,
            'tin' => $vendor->tin,
        ];
    }

    /**
     * All VendorPayment records for a vendor in a calendar quarter.
     */
    public function quartersPayments(Vendor $vendor, int $quarter, int $year): Collection
    {
        [$startMonth, $endMonth] = $this->quarterMonthRange($quarter);

        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = Carbon::create($year, $endMonth)->endOfMonth()->endOfDay();

        return VendorPayment::where('vendor_id', $vendor->id)
            ->whereBetween('payment_date', [$start, $end])
            ->with('vendorInvoice')
            ->orderBy('payment_date')
            ->get();
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Returns the [first_month, last_month] for a given quarter (1–4).
     *
     * @return array{int, int}
     */
    private function quarterMonthRange(int $quarter): array
    {
        return match ($quarter) {
            1 => [1, 3],
            2 => [4, 6],
            3 => [7, 9],
            4 => [10, 12],
            default => throw new DomainException("Quarter must be 1–4, got {$quarter}.", 'AP_INVALID_QUARTER', 422),
        };
    }

    /**
     * Back-calculates EWT for a single payment from its invoice's snapshot rate.
     * (The amount stored on the invoice is the authoritative EWT; we pro-rate if partial.)
     */
    private function ewtForPayment(VendorPayment $payment): float
    {
        $invoice = $payment->vendorInvoice;
        if (! $invoice || (float) $invoice->net_payable <= 0) {
            return 0.00;
        }

        // Pro-rate the invoice's EWT to this payment's share of net_payable
        $share = (float) $payment->amount / (float) $invoice->net_payable;

        return round((float) $invoice->ewt_amount * $share, 2);
    }
}
