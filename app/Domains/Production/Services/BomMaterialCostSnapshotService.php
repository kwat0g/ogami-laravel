<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomMaterialCostSnapshot;
use App\Shared\Contracts\ServiceContract;

final class BomMaterialCostSnapshotService implements ServiceContract
{
    public function __construct(private readonly CostingService $costingService) {}

    public function record(BillOfMaterials $bom, string $source = 'rollup', ?int $actorId = null): BomMaterialCostSnapshot
    {
        $breakdown = $this->costingService->standardCost($bom, 'material_only');

        return BomMaterialCostSnapshot::create([
            'bom_id' => $bom->id,
            'bom_version' => $bom->version,
            'material_cost_centavos' => (int) ($breakdown['material_cost_centavos'] ?? 0),
            'component_lines' => $breakdown['components'] ?? [],
            'source' => $source,
            'created_by_id' => $actorId,
        ]);
    }
}
