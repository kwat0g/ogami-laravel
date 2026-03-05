<?php

declare(strict_types=1);

namespace App\Domains\Tax\Services;

use App\Domains\Tax\Models\VatLedger;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * VAT Ledger Service — VAT-004 enforcement.
 *
 * VAT-004: net_vat = output_vat - input_vat.
 *          If net_vat for the period is negative after closing,
 *          the absolute value is carried forward to the next period's
 *          carry_forward_from_prior, reducing the next period's vat_payable.
 */
final class VatLedgerService implements ServiceContract
{
    // ── Read / Initialise ─────────────────────────────────────────────────────

    /** Retrieve or create the ledger row for a fiscal period. */
    public function getOrCreateForPeriod(int $fiscalPeriodId): VatLedger
    {
        return VatLedger::firstOrCreate(
            ['fiscal_period_id' => $fiscalPeriodId],
            [
                'input_vat' => 0.00,
                'output_vat' => 0.00,
                'carry_forward_from_prior' => 0.00,
            ]
        );
    }

    public function getForPeriod(int $fiscalPeriodId): ?VatLedger
    {
        return VatLedger::where('fiscal_period_id', $fiscalPeriodId)->first();
    }

    // ── Accumulation ──────────────────────────────────────────────────────────

    /**
     * Called by VendorInvoiceService::approve() when input VAT > 0.
     * Uses an atomic increment to avoid race conditions.
     */
    public function accumulateInputVat(int $fiscalPeriodId, float $amount): void
    {
        $this->assertPeriodOpen($fiscalPeriodId);

        DB::transaction(function () use ($fiscalPeriodId, $amount) {
            $ledger = $this->getOrCreateForPeriod($fiscalPeriodId);
            $ledger->increment('input_vat', $amount);
        });
    }

    /**
     * Called by CustomerInvoiceService::approve() when output VAT > 0.
     */
    public function accumulateOutputVat(int $fiscalPeriodId, float $amount): void
    {
        $this->assertPeriodOpen($fiscalPeriodId);

        DB::transaction(function () use ($fiscalPeriodId, $amount) {
            $ledger = $this->getOrCreateForPeriod($fiscalPeriodId);
            $ledger->increment('output_vat', $amount);
        });
    }

    // ── Close Period ──────────────────────────────────────────────────────────

    /**
     * VAT-004: close the period.
     * If vat_payable < 0, carry the absolute surplus to the next period.
     */
    public function closePeriod(VatLedger $ledger, int $userId, ?int $nextFiscalPeriodId = null): VatLedger
    {
        if ($ledger->is_closed) {
            throw new DomainException(
                'VAT period is already closed.',
                'TAX_PERIOD_ALREADY_CLOSED',
                422
            );
        }

        return DB::transaction(function () use ($ledger, $userId, $nextFiscalPeriodId) {
            $ledger->close($userId);

            $vatPayable = $ledger->vat_payable;

            // VAT-004: negative vat_payable → carry excess input VAT forward
            if ($vatPayable < 0 && $nextFiscalPeriodId !== null) {
                $carryForward = abs($vatPayable);
                $nextLedger = $this->getOrCreateForPeriod($nextFiscalPeriodId);

                $nextLedger->update([
                    'carry_forward_from_prior' => round(
                        (float) $nextLedger->carry_forward_from_prior + $carryForward,
                        2
                    ),
                ]);
            }

            return $ledger->fresh();
        });
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    /** Tabular summary for the TaxPeriodSummaryPage. */
    public function summaryForPeriods(array $fiscalPeriodIds): \Illuminate\Support\Collection
    {
        return VatLedger::whereIn('fiscal_period_id', $fiscalPeriodIds)
            ->with('closedByUser')
            ->orderBy('fiscal_period_id')
            ->get();
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function assertPeriodOpen(int $fiscalPeriodId): void
    {
        $ledger = $this->getForPeriod($fiscalPeriodId);

        if ($ledger && $ledger->is_closed) {
            throw new DomainException(
                "VAT ledger for fiscal period {$fiscalPeriodId} is closed. Cannot accumulate VAT.",
                'TAX_PERIOD_CLOSED',
                422
            );
        }
    }
}
