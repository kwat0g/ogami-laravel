<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class BomService implements ServiceContract
{
    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = BillOfMaterials::with('productItem', 'components.componentItem')
            ->orderByDesc('id');

        if ($filters['with_archived'] ?? false) {
            $query->withTrashed();
        }

        if (isset($filters['product_item_id'])) {
            $query->where('product_item_id', $filters['product_item_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data): BillOfMaterials
    {
        return \DB::transaction(function () use ($data): BillOfMaterials {
            /** @var BillOfMaterials $bom */
            $bom = BillOfMaterials::create([
                'product_item_id' => $data['product_item_id'],
                'version' => $data['version'] ?? '1.0',
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['components'] ?? [] as $comp) {
                BomComponent::create([
                    'bom_id' => $bom->id,
                    'component_item_id' => $comp['component_item_id'],
                    'qty_per_unit' => $comp['qty_per_unit'],
                    'unit_of_measure' => $comp['unit_of_measure'],
                    'scrap_factor_pct' => $comp['scrap_factor_pct'] ?? 0,
                ]);
            }

            // Auto-calculate standard cost on BOM creation (thesis-grade: BOM
            // must always reflect its actual material/labor/overhead cost, not
            // require a separate manual rollup step).
            $bom->load('productItem', 'components.componentItem');
            $this->autoRollupCost($bom);

            return $bom->fresh(['productItem', 'components.componentItem']) ?? $bom;
        });
    }

    /** @param array<string,mixed> $data */
    public function update(BillOfMaterials $bom, array $data): BillOfMaterials
    {
        return \DB::transaction(function () use ($bom, $data): BillOfMaterials {
            $bom->update([
                'version' => $data['version'] ?? $bom->version,
                'is_active' => $data['is_active'] ?? $bom->is_active,
                'notes' => $data['notes'] ?? $bom->notes,
            ]);

            $componentsChanged = false;

            if (isset($data['components'])) {
                $bom->components()->delete();
                foreach ($data['components'] as $comp) {
                    BomComponent::create([
                        'bom_id' => $bom->id,
                        'component_item_id' => $comp['component_item_id'],
                        'qty_per_unit' => $comp['qty_per_unit'],
                        'unit_of_measure' => $comp['unit_of_measure'],
                        'scrap_factor_pct' => $comp['scrap_factor_pct'] ?? 0,
                    ]);
                }
                $componentsChanged = true;
            }

            $bom = $bom->fresh(['productItem', 'components.componentItem']) ?? $bom;

            // Auto-recalculate cost when components change (keeps BOM cost
            // always in sync with its material composition).
            if ($componentsChanged) {
                $this->autoRollupCost($bom);
                $bom = $bom->fresh(['productItem', 'components.componentItem']) ?? $bom;
            }

            return $bom;
        });
    }

    public function activate(BillOfMaterials $bom): BillOfMaterials
    {
        $bom->update(['is_active' => true]);

        return $bom->fresh(['productItem', 'components.componentItem']) ?? $bom;
    }

    public function archive(BillOfMaterials $bom): void
    {
        \DB::transaction(function () use ($bom): void {
            $bom->update(['is_active' => false]);
            $bom->delete();
        });
    }

    /** @param array<string,mixed> $filters */
    public function allForItem(int $itemId): Collection
    {
        return BillOfMaterials::with('components.componentItem')
            ->where('product_item_id', $itemId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Rollup standard cost for a BOM.
     *
     * Uses CostingService for multi-level BOM explosion (recurses into
     * sub-assembly BOMs) and includes routing labor + overhead costs from
     * work centers. Persists the total on standard_cost_centavos.
     *
     * @param  string  $costElements  'material_only' | 'material_labor' | 'material_labor_overhead'
     */
    public function rollupCost(BillOfMaterials $bom, string $costElements = 'material_labor_overhead'): BillOfMaterials
    {
        $costingService = app(CostingService::class);
        $result = $costingService->standardCost($bom, $costElements);

        $bom->update([
            'standard_cost_centavos' => $result['total_standard_cost_centavos'],
            'last_cost_rollup_at' => now(),
        ]);

        return $bom->fresh(['productItem', 'components.componentItem']) ?? $bom;
    }

    /**
     * Where-used report: find all BOMs that use a specific item as a component.
     *
     * @return \Illuminate\Support\Collection<int, array{bom_id: int, product_item_id: int, product_name: string, bom_version: string, qty_per_unit: float, is_active: bool}>
     */
    public function whereUsed(int $itemId): \Illuminate\Support\Collection
    {
        $costingService = app(CostingService::class);

        return $costingService->whereUsed($itemId);
    }

    /**
     * Get cost breakdown for a BOM without persisting anything.
     *
     * Returns the full material + labor + overhead breakdown for display
     * in the BOM detail view (thesis-grade: cost visibility at all times).
     *
     * @return array{material_cost_centavos: int, labor_cost_centavos: int, overhead_cost_centavos: int, total_standard_cost_centavos: int, components: array, routings: array}
     */
    public function getCostBreakdown(BillOfMaterials $bom, string $costElements = 'material_labor_overhead'): array
    {
        $costingService = app(CostingService::class);

        return $costingService->standardCost($bom, $costElements);
    }

    /**
     * Auto-calculate and persist standard cost on a BOM.
     *
     * Called automatically on create/update so the BOM always reflects
     * current material costs. Silently handles cases where components
     * have no pricing yet (cost will be 0 until prices are set).
     */
    private function autoRollupCost(BillOfMaterials $bom): void
    {
        try {
            $costingService = app(CostingService::class);
            $result = $costingService->standardCost($bom, 'material_labor_overhead');

            $bom->update([
                'standard_cost_centavos' => $result['total_standard_cost_centavos'],
                'last_cost_rollup_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Don't fail BOM creation/update if cost calculation has issues
            // (e.g., circular BOM, missing item prices). Cost will be 0 until
            // the issue is resolved and a manual rollup is triggered.
            \Illuminate\Support\Facades\Log::warning('[Production] Auto cost rollup failed for BOM', [
                'bom_id' => $bom->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Compare cost between two BOM versions for the same product.
     *
     * @return array{version_a: array, version_b: array, variance_centavos: int, variance_pct: float}
     */
    public function compareCost(BillOfMaterials $bomA, BillOfMaterials $bomB): array
    {
        $costingService = app(CostingService::class);

        $costA = $costingService->standardCost($bomA);
        $costB = $costingService->standardCost($bomB);

        $variance = $costB['material_cost_centavos'] - $costA['material_cost_centavos'];
        $variancePct = $costA['material_cost_centavos'] > 0
            ? round(($variance / $costA['material_cost_centavos']) * 100, 2)
            : 0.0;

        return [
            'version_a' => [
                'bom_id' => $bomA->id,
                'version' => $bomA->version,
                'material_cost_centavos' => $costA['material_cost_centavos'],
            ],
            'version_b' => [
                'bom_id' => $bomB->id,
                'version' => $bomB->version,
                'material_cost_centavos' => $costB['material_cost_centavos'],
            ],
            'variance_centavos' => $variance,
            'variance_pct' => $variancePct,
        ];
    }
}
