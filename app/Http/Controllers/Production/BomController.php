<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Services\BomService;
use App\Domains\Production\Services\CostingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StoreBomRequest;
use App\Http\Resources\Production\BomResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class BomController extends Controller
{
    public function __construct(private readonly BomService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BillOfMaterials::class);

        $page = $this->service->paginate($request->only([
            'product_item_id', 'is_active', 'per_page', 'with_archived',
        ]));

        return BomResource::collection($page);
    }

    public function store(StoreBomRequest $request): BomResource
    {
        $this->authorize('create', BillOfMaterials::class);

        return new BomResource($this->service->store($request->validated()));
    }

    public function show(BillOfMaterials $bom): BomResource
    {
        $this->authorize('view', $bom);

        return new BomResource($bom->load('productItem', 'components.componentItem'));
    }

    public function update(StoreBomRequest $request, BillOfMaterials $bom): BomResource
    {
        $this->authorize('update', $bom);

        return new BomResource($this->service->update($bom, $request->validated()));
    }

    public function activate(BillOfMaterials $bom): BomResource
    {
        $this->authorize('update', $bom);

        return new BomResource($this->service->activate($bom));
    }

    public function destroy(Request $request, BillOfMaterials $bom): JsonResponse
    {
        $this->authorize('update', $bom);

        $this->service->archive($bom, $request->user());

        return response()->json(['message' => 'BOM archived.']);
    }

    /** List archived (soft-deleted) BOMs. */
    public function archived(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BillOfMaterials::class);

        return BomResource::collection(
            $this->service->listArchived(
                perPage: $request->integer('per_page', 20),
                search: $request->input('search'),
            )
        );
    }

    /** Restore a soft-deleted BOM from the archive. */
    public function restore(Request $request, int $bom): BomResource
    {
        $this->authorize('create', BillOfMaterials::class);

        $restored = $this->service->restore($bom, $request->user());

        return new BomResource($restored->load('productItem', 'components.componentItem'));
    }

    /** Permanently delete a BOM — superadmin only. */
    public function forceDelete(Request $request, int $bom): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403, 'Only super admins can permanently delete records.');

        $this->service->forceDelete($bom, $request->user());

        return response()->json(['message' => 'BOM permanently deleted.']);
    }

    /**
     * Full cost breakdown for a BOM (material + labor + overhead).
     *
     * Returns the detailed component-level and routing-level cost analysis.
     * This is the "Bill" part of "Bill of Materials" — always available,
     * not requiring a separate rollup action.
     */
    public function costBreakdown(BillOfMaterials $bom): JsonResponse
    {
        $this->authorize('view', $bom);

        return response()->json([
            'data' => $this->service->getCostBreakdown($bom),
        ]);
    }

    /**
     * Rollup and persist standard cost on the BOM.
     */
    public function rollupCost(BillOfMaterials $bom): BomResource
    {
        $this->authorize('update', $bom);

        return new BomResource($this->service->rollupCost($bom));
    }

    /**
     * Compare cost between two BOM versions.
     */
    public function costCompare(Request $request, BillOfMaterials $bom): JsonResponse
    {
        $this->authorize('view', $bom);

        $request->validate([
            'compare_bom_id' => ['required', 'integer', 'exists:bill_of_materials,id'],
        ]);

        $compareBom = BillOfMaterials::findOrFail($request->integer('compare_bom_id'));

        return response()->json([
            'data' => $this->service->compareCost($bom, $compareBom),
        ]);
    }
}
