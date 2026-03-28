<?php

declare(strict_types=1);

namespace App\Domains\FixedAssets\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryLine;
use App\Domains\FixedAssets\Models\FixedAsset;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Asset Revaluation & Impairment Service — Items 48 & 49.
 *
 * Revaluation (IAS 16): adjust carrying value to fair value, post surplus to equity.
 * Impairment (IAS 36): reduce carrying value to recoverable amount, post loss to P&L.
 *
 * Both post journal entries automatically.
 */
final class AssetRevaluationService implements ServiceContract
{
    /**
     * Revalue an asset to its fair market value.
     * Surplus goes to Revaluation Surplus (equity account).
     * Deficit goes to P&L unless there is existing surplus.
     *
     * @return array{asset_id: int, old_value_centavos: int, new_value_centavos: int, difference_centavos: int, journal_entry_id: int|null}
     */
    public function revalue(FixedAsset $asset, int $fairValueCentavos, User $actor, string $reason): array
    {
        if (! in_array($asset->status, ['active', 'fully_depreciated'], true)) {
            throw new DomainException('Only active or fully depreciated assets can be revalued.', 'FA_INVALID_STATUS', 422);
        }

        $currentCarrying = $this->carryingValue($asset);
        $difference = $fairValueCentavos - $currentCarrying;

        if ($difference === 0) {
            return [
                'asset_id' => $asset->id,
                'old_value_centavos' => $currentCarrying,
                'new_value_centavos' => $fairValueCentavos,
                'difference_centavos' => 0,
                'journal_entry_id' => null,
            ];
        }

        return DB::transaction(function () use ($asset, $fairValueCentavos, $currentCarrying, $difference, $actor, $reason): array {
            // Update asset cost to reflect new value
            $asset->update([
                'acquisition_cost_centavos' => $asset->acquisition_cost_centavos + $difference,
                'last_revaluation_at' => now(),
                'revaluation_notes' => $reason,
            ]);

            // Post journal entry
            $jeId = $this->postRevaluationJE($asset, $difference, $actor, $reason);

            return [
                'asset_id' => $asset->id,
                'old_value_centavos' => $currentCarrying,
                'new_value_centavos' => $fairValueCentavos,
                'difference_centavos' => $difference,
                'type' => $difference > 0 ? 'surplus' : 'deficit',
                'journal_entry_id' => $jeId,
            ];
        });
    }

    /**
     * Test for impairment: if recoverable amount < carrying value, record loss.
     *
     * @return array{asset_id: int, carrying_value_centavos: int, recoverable_amount_centavos: int, impairment_loss_centavos: int, impaired: bool, journal_entry_id: int|null}
     */
    public function impairmentTest(FixedAsset $asset, int $recoverableAmountCentavos, User $actor): array
    {
        $carryingValue = $this->carryingValue($asset);
        $loss = max(0, $carryingValue - $recoverableAmountCentavos);

        if ($loss === 0) {
            return [
                'asset_id' => $asset->id,
                'carrying_value_centavos' => $carryingValue,
                'recoverable_amount_centavos' => $recoverableAmountCentavos,
                'impairment_loss_centavos' => 0,
                'impaired' => false,
                'journal_entry_id' => null,
            ];
        }

        return DB::transaction(function () use ($asset, $carryingValue, $recoverableAmountCentavos, $loss, $actor): array {
            // Update asset status and record impairment
            $asset->update([
                'status' => 'impaired',
                'impairment_loss_centavos' => ($asset->impairment_loss_centavos ?? 0) + $loss,
            ]);

            // Post impairment loss journal entry
            $jeId = $this->postImpairmentJE($asset, $loss, $actor);

            return [
                'asset_id' => $asset->id,
                'carrying_value_centavos' => $carryingValue,
                'recoverable_amount_centavos' => $recoverableAmountCentavos,
                'impairment_loss_centavos' => $loss,
                'impaired' => true,
                'journal_entry_id' => $jeId,
            ];
        });
    }

    private function carryingValue(FixedAsset $asset): int
    {
        $cost = (int) ($asset->acquisition_cost_centavos ?? 0);
        $accDepn = (int) ($asset->accumulated_depreciation_centavos ?? 0);
        $impairment = (int) ($asset->impairment_loss_centavos ?? 0);

        return max(0, $cost - $accDepn - $impairment);
    }

    private function postRevaluationJE(FixedAsset $asset, int $difference, User $actor, string $reason): ?int
    {
        $period = FiscalPeriod::where('status', 'open')->orderByDesc('end_date')->first();
        if ($period === null) {
            return null;
        }

        $assetAccount = ChartOfAccount::where('name', 'ILIKE', '%fixed asset%')->first();
        $surplusAccount = ChartOfAccount::where('name', 'ILIKE', '%revaluation%surplus%')
            ->orWhere('name', 'ILIKE', '%revaluation%reserve%')
            ->first();

        if ($assetAccount === null) {
            return null;
        }

        $je = JournalEntry::create([
            'fiscal_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'reference_number' => "REVAL-{$asset->id}",
            'description' => "Asset revaluation: {$asset->asset_code} — {$reason}",
            'source_type' => 'fixed_asset',
            'status' => 'posted',
            'posted_by' => null,
            'posted_at' => now(),
            'created_by_id' => $actor->id,
        ]);

        $absDiff = abs($difference);

        if ($difference > 0) {
            // Surplus: debit asset, credit revaluation surplus
            JournalEntryLine::create(['journal_entry_id' => $je->id, 'account_id' => $assetAccount->id, 'debit_centavos' => $absDiff, 'credit_centavos' => 0]);
            if ($surplusAccount) {
                JournalEntryLine::create(['journal_entry_id' => $je->id, 'account_id' => $surplusAccount->id, 'debit_centavos' => 0, 'credit_centavos' => $absDiff]);
            }
        } else {
            // Deficit: debit revaluation loss (P&L), credit asset
            JournalEntryLine::create(['journal_entry_id' => $je->id, 'account_id' => $assetAccount->id, 'debit_centavos' => 0, 'credit_centavos' => $absDiff]);
            if ($surplusAccount) {
                JournalEntryLine::create(['journal_entry_id' => $je->id, 'account_id' => $surplusAccount->id, 'debit_centavos' => $absDiff, 'credit_centavos' => 0]);
            }
        }

        return $je->id;
    }

    private function postImpairmentJE(FixedAsset $asset, int $loss, User $actor): ?int
    {
        $period = FiscalPeriod::where('status', 'open')->orderByDesc('end_date')->first();
        if ($period === null) {
            return null;
        }

        $impairmentExpense = ChartOfAccount::where('name', 'ILIKE', '%impairment%loss%')->first();
        $accImpairment = ChartOfAccount::where('name', 'ILIKE', '%accumulated%impairment%')
            ->orWhere('name', 'ILIKE', '%impairment%allowance%')
            ->first();

        $je = JournalEntry::create([
            'fiscal_period_id' => $period->id,
            'entry_date' => now()->toDateString(),
            'reference_number' => "IMPAIR-{$asset->id}",
            'description' => "Asset impairment loss: {$asset->asset_code}",
            'source_type' => 'fixed_asset',
            'status' => 'posted',
            'posted_by' => null,
            'posted_at' => now(),
            'created_by_id' => $actor->id,
        ]);

        if ($impairmentExpense) {
            JournalEntryLine::create(['journal_entry_id' => $je->id, 'account_id' => $impairmentExpense->id, 'debit_centavos' => $loss, 'credit_centavos' => 0]);
        }
        if ($accImpairment) {
            JournalEntryLine::create(['journal_entry_id' => $je->id, 'account_id' => $accImpairment->id, 'debit_centavos' => 0, 'credit_centavos' => $loss]);
        }

        return $je->id;
    }
}
