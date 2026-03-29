<?php

declare(strict_types=1);

namespace App\Domains\FixedAssets\Services;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\JournalEntryService;
use App\Domains\FixedAssets\Models\AssetDepreciationEntry;
use App\Domains\FixedAssets\Models\AssetDisposal;
use App\Domains\FixedAssets\Models\FixedAsset;
use App\Domains\FixedAssets\Models\FixedAssetCategory;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Traits\HasArchiveOperations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fixed Asset lifecycle management.
 *
 * FA-001: Register capitalised assets with GL account linkage via category.
 * FA-002: `depreciateMonth()` calculates straight-line / double-declining per asset
 *         and posts a GL JE (Dr Depreciation Expense / Cr Accumulated Depreciation).
 * FA-003: `dispose()` retires an asset and posts a gain/loss JE.
 * FA-004: Fully-depreciated assets are skipped automatically.
 */
final class FixedAssetService implements ServiceContract
{
    use HasArchiveOperations;
    public function __construct(private readonly JournalEntryService $jeService) {}

    // ── Asset Registration ───────────────────────────────────────────────────

    /**
     * @param  array{name: string, category_id: int, acquisition_date: string, acquisition_cost_centavos: int, residual_value_centavos?: int, useful_life_years?: int, depreciation_method?: string, department_id?: int, description?: string, serial_number?: string, location?: string, purchased_from?: string, purchase_invoice_ref?: string}  $data
     */
    public function register(array $data, User $actor): FixedAsset
    {
        return DB::transaction(function () use ($data, $actor): FixedAsset {
            /** @var FixedAssetCategory $category */
            $category = FixedAssetCategory::findOrFail($data['category_id']);

            return FixedAsset::create([
                'asset_code' => '', // populated by DB trigger
                'category_id' => $category->id,
                'department_id' => $data['department_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'location' => $data['location'] ?? null,
                'acquisition_date' => $data['acquisition_date'],
                'acquisition_cost_centavos' => $data['acquisition_cost_centavos'],
                'residual_value_centavos' => $data['residual_value_centavos'] ?? 0,
                'useful_life_years' => $data['useful_life_years'] ?? $category->default_useful_life_years,
                'depreciation_method' => $data['depreciation_method'] ?? $category->default_depreciation_method,
                'accumulated_depreciation_centavos' => 0,
                'status' => 'active',
                'purchased_from' => $data['purchased_from'] ?? null,
                'purchase_invoice_ref' => $data['purchase_invoice_ref'] ?? null,
                'created_by_id' => $actor->id,
            ]);
        });
    }

    // ── Monthly Depreciation ─────────────────────────────────────────────────

    /**
     * Depreciate all eligible active assets for the given fiscal period.
     *
     * REC-02: Returns structured results tracking succeeded, failed, and skipped
     * assets instead of a bare count. Failures are logged at critical level so
     * silent GL skips (where category GL accounts are null) are surfaced.
     *
     * @return array{succeeded: int, failed: list<array{asset_id: int, asset_code: string, error: string}>, skipped: int, total: int}
     */
    public function depreciateMonth(FiscalPeriod $period, User $actor): array
    {
        $assets = FixedAsset::where('status', 'active')
            ->with('category')
            ->get();

        $succeeded = 0;
        $failed = [];
        $skipped = 0;

        foreach ($assets as $asset) {
            // Skip already-processed assets for this period
            $alreadyRun = AssetDepreciationEntry::where('fixed_asset_id', $asset->id)
                ->where('fiscal_period_id', $period->id)
                ->exists();

            if ($alreadyRun) {
                $skipped++;
                continue;
            }

            $depAmount = $this->computeDepreciation($asset);

            if ($depAmount <= 0) {
                // Mark fully depreciated if no remaining amount
                $asset->update(['status' => 'fully_depreciated']);
                $skipped++;

                continue;
            }

            try {
                $this->postDepreciationEntry($asset, $period, $depAmount, $actor);
                $succeeded++;
            } catch (\Throwable $e) {
                Log::critical('Fixed asset depreciation GL posting failed', [
                    'asset_id' => $asset->id,
                    'asset_code' => $asset->asset_code,
                    'category_id' => $asset->category_id,
                    'error' => $e->getMessage(),
                ]);
                $failed[] = [
                    'asset_id' => $asset->id,
                    'asset_code' => $asset->asset_code ?? '',
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (! empty($failed)) {
            Log::critical('Fixed asset depreciation completed with failures', [
                'period_id' => $period->id,
                'succeeded' => $succeeded,
                'failed_count' => count($failed),
                'failed_assets' => $failed,
            ]);
        }

        return [
            'succeeded' => $succeeded,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => $assets->count(),
        ];
    }

    // ── Disposal ─────────────────────────────────────────────────────────────

    /**
     * @param  array{disposal_date: string, proceeds_centavos?: int, disposal_method?: string, notes?: string}  $data
     */
    public function dispose(FixedAsset $asset, array $data, User $actor): AssetDisposal
    {
        if ($asset->status === 'disposed') {
            throw new DomainException('Asset is already disposed.', 'FA_ALREADY_DISPOSED', 422);
        }

        return DB::transaction(function () use ($asset, $data, $actor): AssetDisposal {
            $proceeds = $data['proceeds_centavos'] ?? 0;
            $bookValue = $asset->bookValueCentavos();
            $gainLoss = $proceeds - $bookValue;

            $je = null;
            $category = $asset->category;

            // FA-GL-001: GL accounts MUST be configured — throw instead of silently skipping
            if ($category === null ||
                $category->gl_asset_account_id === null ||
                $category->gl_accumulated_depreciation_account_id === null) {
                throw new DomainException(
                    'Cannot post disposal journal entry: GL accounts (asset, accumulated depreciation) are not configured on the asset category. '
                    .'Configure GL accounts on the category before disposing this asset.',
                    'FA_GL_NOT_CONFIGURED',
                    422,
                    ['fixed_asset_id' => $asset->id, 'category_id' => $category?->id],
                );
            }

            $je = $this->postDisposalJe($asset, $category, $proceeds, $gainLoss, $data['disposal_date'], $actor);

            $disposal = AssetDisposal::create([
                'fixed_asset_id' => $asset->id,
                'disposal_date' => $data['disposal_date'],
                'proceeds_centavos' => $proceeds,
                'disposal_method' => $data['disposal_method'] ?? 'write_off',
                'gain_loss_centavos' => $gainLoss,
                'journal_entry_id' => $je->id,
                'notes' => $data['notes'] ?? null,
                'created_by_id' => $actor->id,
            ]);

            $asset->update([
                'status' => 'disposed',
                'disposal_date' => $data['disposal_date'],
            ]);

            return $disposal;
        });
    }

    // ── Category management ──────────────────────────────────────────────────

    /**
     * @param  array{name: string, code_prefix: string, default_useful_life_years?: int, default_depreciation_method?: string, gl_asset_account_id?: int|null, gl_depreciation_expense_account_id?: int|null, gl_accumulated_depreciation_account_id?: int|null}  $data
     */
    public function storeCategory(array $data, User $actor): FixedAssetCategory
    {
        return DB::transaction(function () use ($data, $actor): FixedAssetCategory {
            return FixedAssetCategory::create(array_merge($data, ['created_by_id' => $actor->id]));
        });
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function computeDepreciation(FixedAsset $asset): int
    {
        $depreciable = $asset->depreciableAmountCentavos();

        if ($depreciable <= 0) {
            return 0;
        }

        return match ($asset->depreciation_method) {
            'straight_line' => $this->straightLine($asset),
            'double_declining' => $this->doubleDeclining($asset),
            default => $this->straightLine($asset),
        };
    }

    private function straightLine(FixedAsset $asset): int
    {
        $depreciable = $asset->acquisition_cost_centavos - $asset->residual_value_centavos;
        $monthlyRate = $depreciable / ($asset->useful_life_years * 12);

        return (int) min(round($monthlyRate), $asset->depreciableAmountCentavos());
    }

    private function doubleDeclining(FixedAsset $asset): int
    {
        $bookValue = $asset->bookValueCentavos();
        $annualRate = 2 / $asset->useful_life_years;
        $monthly = ($bookValue * $annualRate) / 12;

        // Never depreciate below residual value
        $remaining = $bookValue - $asset->residual_value_centavos;

        return (int) min(round($monthly), max(0, $remaining));
    }

    private function postDepreciationEntry(
        FixedAsset $asset,
        FiscalPeriod $period,
        int $depAmountCentavos,
        User $actor,
    ): AssetDepreciationEntry {
        return DB::transaction(function () use ($asset, $period, $depAmountCentavos, $actor): AssetDepreciationEntry {
            $category = $asset->category;

            // FA-GL-001: GL accounts MUST be configured — throw instead of silently skipping
            if ($category === null ||
                $category->gl_depreciation_expense_account_id === null ||
                $category->gl_accumulated_depreciation_account_id === null) {
                throw new DomainException(
                    'Cannot post depreciation journal entry: GL accounts (depreciation expense, accumulated depreciation) are not configured on the asset category. '
                    .'Configure GL accounts on the category before running depreciation.',
                    'FA_GL_NOT_CONFIGURED',
                    422,
                    ['fixed_asset_id' => $asset->id, 'category_id' => $category?->id],
                );
            }

            $depFloat = $depAmountCentavos / 100;

            $je = $this->jeService->create([
                'date' => $period->date_to,
                'description' => "Depreciation — {$asset->name} ({$asset->asset_code})",
                'source_type' => 'fixed_assets',
                'source_id' => $asset->id,
                'lines' => [
                    ['account_id' => $category->gl_depreciation_expense_account_id, 'debit' => $depFloat, 'credit' => null],
                    ['account_id' => $category->gl_accumulated_depreciation_account_id, 'debit' => null, 'credit' => $depFloat],
                ],
            ]);

            $entry = AssetDepreciationEntry::create([
                'fixed_asset_id' => $asset->id,
                'fiscal_period_id' => $period->id,
                'depreciation_amount_centavos' => $depAmountCentavos,
                'method' => $asset->depreciation_method,
                'journal_entry_id' => $je->id,
                'computed_by_id' => $actor->id,
            ]);

            $newAccumulated = $asset->accumulated_depreciation_centavos + $depAmountCentavos;
            $fullyDep = $newAccumulated >= ($asset->acquisition_cost_centavos - $asset->residual_value_centavos);

            $asset->update([
                'accumulated_depreciation_centavos' => $newAccumulated,
                'status' => $fullyDep ? 'fully_depreciated' : 'active',
            ]);

            return $entry;
        });
    }

    private function postDisposalJe(
        FixedAsset $asset,
        FixedAssetCategory $category,
        int $proceedsCentavos,
        int $gainLossCentavos,
        string $disposalDate,
        User $actor,
    ): JournalEntry {
        $costFloat = $asset->acquisition_cost_centavos / 100;
        $accumDepFloat = $asset->accumulated_depreciation_centavos / 100;
        $proceedsFloat = $proceedsCentavos / 100;

        $lines = [
            // Remove accumulated depreciation
            ['account_id' => $category->gl_accumulated_depreciation_account_id, 'debit' => $accumDepFloat, 'credit' => null],
        ];

        if ($proceedsCentavos > 0) {
            // Cash/receivable for proceeds — use the same asset account as a proxy (real ERP would use a cash account)
            $lines[] = ['account_id' => $category->gl_asset_account_id, 'debit' => $proceedsFloat, 'credit' => null, 'description' => 'Disposal proceeds'];
        }

        // Remove original asset cost
        $lines[] = ['account_id' => $category->gl_asset_account_id, 'debit' => null, 'credit' => $costFloat];

        // Gain / loss balancing line — use accumulated depreciation account as proxy for gain/loss
        if ($gainLossCentavos !== 0) {
            $gainFloat = abs($gainLossCentavos) / 100;
            if ($gainLossCentavos > 0) {
                // Gain on disposal — credit
                $lines[] = ['account_id' => $category->gl_accumulated_depreciation_account_id, 'debit' => null, 'credit' => $gainFloat, 'description' => 'Gain on disposal'];
            } else {
                // Loss on disposal — debit
                $lines[] = ['account_id' => $category->gl_depreciation_expense_account_id ?? $category->gl_asset_account_id, 'debit' => $gainFloat, 'credit' => null, 'description' => 'Loss on disposal'];
            }
        }

        return $this->jeService->create([
            'date' => $disposalDate,
            'description' => "Asset disposal — {$asset->name} ({$asset->asset_code})",
            'source_type' => 'fixed_assets',
            'source_id' => $asset->id,
            'lines' => $lines,
        ]);
    }
}
