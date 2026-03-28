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

            return $bom->load('productItem', 'components.componentItem');
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
            }

            return $bom->fresh(['productItem', 'components.componentItem']) ?? $bom;
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
     * Computes material cost from components (single-level) and persists
     * the result on the BOM's standard_cost_centavos column.
     */
    public function rollupCost(BillOfMaterials $bom): BillOfMaterials
    {
        $bom->loadMissing(['components.componentItem']);

        $totalCost = 0;

        foreach ($bom->components as $comp) {
            $item = $comp->componentItem;
            $unitCost = (int) ($item->standard_price_centavos ?? 0);
            $qtyPerUnit = (float) $comp->qty_per_unit;
            $scrapFactor = 1 + ((float) $comp->scrap_factor_pct / 100);
            $grossQty = $qtyPerUnit * $scrapFactor;
            $totalCost += (int) round($grossQty * $unitCost);
        }

        $bom->update([
            'standard_cost_centavos' => $totalCost,
            'last_cost_rollup_at' => now(),
        ]);

        return $bom->fresh(['productItem', 'components.componentItem']) ?? $bom;
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
