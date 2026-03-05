<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Services\ItemMasterService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreItemMasterRequest;
use App\Http\Resources\Inventory\ItemMasterResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class ItemMasterController extends Controller
{
    public function __construct(private readonly ItemMasterService $service) {}

    public function index(Request $request): ResourceCollection
    {
        $this->authorize('viewAny', ItemMaster::class);

        $query = ItemMaster::with('category')
            ->when($request->input('category_id'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->when($request->input('search'), fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('name', 'ilike', "%{$v}%")
                    ->orWhere('item_code', 'ilike', "%{$v}%");
            }))
            ->orderBy('name');

        return ItemMasterResource::collection($query->paginate(50));
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => $this->service->allCategories()]);
    }

    public function lowStock(): ResourceCollection
    {
        $this->authorize('viewAny', ItemMaster::class);
        return ItemMasterResource::collection($this->service->lowStockItems());
    }

    public function store(StoreItemMasterRequest $request): ItemMasterResource
    {
        $this->authorize('create', ItemMaster::class);
        $item = $this->service->store($request->validated());
        return new ItemMasterResource($item->load('category'));
    }

    public function show(ItemMaster $item): ItemMasterResource
    {
        $this->authorize('view', $item);
        return new ItemMasterResource($item->load(['category', 'stockBalances.location']));
    }

    public function update(StoreItemMasterRequest $request, ItemMaster $item): ItemMasterResource
    {
        $this->authorize('update', $item);
        $updated = $this->service->update($item, $request->validated());
        return new ItemMasterResource($updated->load('category'));
    }

    public function toggleActive(ItemMaster $item): ItemMasterResource
    {
        $this->authorize('update', $item);
        return new ItemMasterResource($this->service->toggleActive($item));
    }
}
