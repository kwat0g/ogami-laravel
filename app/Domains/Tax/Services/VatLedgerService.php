<?php

declare(strict_types=1);

namespace App\Domains\Tax\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\JournalEntryService;
use App\Domains\Tax\Models\VatLedger;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Collection;
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
    public function __construct(
        private readonly JournalEntryService $jeService,
    ) {}
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

    // ── H8 FIX: VAT Reversal ──────────────────────────────────────────────────

    /**
     * Reverse previously accumulated input VAT when an AP invoice is
     * rejected after approval or cancelled. Without this, overstated
     * input VAT leads to understated VAT payable — a tax compliance risk.
     */
    public function reverseInputVat(int $fiscalPeriodId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->assertPeriodOpen($fiscalPeriodId);

        DB::transaction(function () use ($fiscalPeriodId, $amount) {
            $ledger = $this->getOrCreateForPeriod($fiscalPeriodId);
            $newInputVat = max(0, (float) $ledger->input_vat - $amount);
            $ledger->update(['input_vat' => round($newInputVat, 2)]);
        });
    }

    /**
     * Reverse previously accumulated output VAT when an AR invoice is
     * cancelled after approval.
     */
    public function reverseOutputVat(int $fiscalPeriodId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->assertPeriodOpen($fiscalPeriodId);

        DB::transaction(function () use ($fiscalPeriodId, $amount) {
            $ledger = $this->getOrCreateForPeriod($fiscalPeriodId);
            $newOutputVat = max(0, (float) $ledger->output_vat - $amount);
            $ledger->update(['output_vat' => round($newOutputVat, 2)]);
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

            // TAX-GL-001: Post a JE to reclassify net VAT payable to BIR Remittable.
            // Requires accounts 2105 (Output VAT Payable) and 2106 (BIR VAT Remittable)
            // to exist in the chart of accounts.
            if ($vatPayable > 0) {
                $this->postVatCloseJournalEntry($vatPayable, $userId);
            }

            return $ledger->fresh();
        });
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    /** Tabular summary for the TaxPeriodSummaryPage. */
    public function summaryForPeriods(array $fiscalPeriodIds): Collection
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

    /**
     * TAX-GL-001: Reclassify net VAT payable to BIR Remittable in the GL.
     *
     * DR Output VAT Payable (2105) — clears the collected output VAT liability
     * CR BIR VAT Remittable (2106) — records the net amount owed to BIR
     *
     * Silently skips posting if either account does not exist in the CoA
     * (graceful degradation for deployments that have not yet run the full seeder).
     */
    private function postVatCloseJournalEntry(float $vatPayable, int $userId): void
    {
        $outputVatAccount = ChartOfAccount::where('code', '2105')->first();
        $vatRemittableAccount = ChartOfAccount::where('code', '2106')->first();

        if ($outputVatAccount === null || $vatRemittableAccount === null) {
            return; // Accounts not yet seeded; skip GL posting
        }

        $vatPayableRounded = round($vatPayable, 2);

        $je = $this->jeService->create([
            'date' => now()->toDateString(),
            'description' => 'VAT Period Close — reclassify output VAT to BIR Remittable',
            'source_type' => 'tax',
            'lines' => [
                [
                    'account_id' => $outputVatAccount->id,
                    'debit' => $vatPayableRounded,
                    'description' => 'Clear output VAT payable on period close',
                ],
                [
                    'account_id' => $vatRemittableAccount->id,
                    'credit' => $vatPayableRounded,
                    'description' => 'Net VAT remittable to BIR',
                ],
            ],
        ]);

        $this->jeService->post($je);
    }
}
