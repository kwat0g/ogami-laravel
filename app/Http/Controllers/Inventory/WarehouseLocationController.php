<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Domains\Inventory\Models\WarehouseLocation;
use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\WarehouseLocationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class WarehouseLocationController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        $this->authorize('viewAny', WarehouseLocation::class);

        $locations = WarehouseLocation::with('department')
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->orderBy('code')
            ->get();

        return WarehouseLocationResource::collection($locations);
    }

    public function store(Request $request): WarehouseLocationResource
    {
        $this->authorize('create', WarehouseLocation::class);

        $data = $request->validate([
            'code' => 'required|string|max:30|unique:warehouse_locations,code',
            'name' => 'required|string|max:100',
            'zone' => 'nullable|string|max:50',
            'bin' => 'nullable|string|max:50',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $location = WarehouseLocation::create($data);

        return new WarehouseLocationResource($location->load('department'));
    }

    public function update(Request $request, WarehouseLocation $warehouseLocation): WarehouseLocationResource
    {
        $this->authorize('update', $warehouseLocation);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'zone' => 'nullable|string|max:50',
            'bin' => 'nullable|string|max:50',
            'department_id' => 'nullable|exists:departments,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $warehouseLocation->update($data);

        return new WarehouseLocationResource($warehouseLocation->load('department'));
    }
}
