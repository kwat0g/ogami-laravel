<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Services\BomService;
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

    public function destroy(BillOfMaterials $bom): JsonResponse
    {
        $this->authorize('update', $bom);

        $this->service->archive($bom);

        return response()->json(['message' => 'BOM archived.']);
    }
}
