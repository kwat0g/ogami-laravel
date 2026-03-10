<?php

declare(strict_types=1);

namespace App\Http\Controllers\AP;

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorItem;
use App\Domains\AP\Services\VendorItemService;
use App\Http\Controllers\Controller;
use App\Http\Resources\AP\VendorItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class VendorItemController extends Controller
{
    public function __construct(
        private readonly VendorItemService $service,
    ) {}

    /** List items for a vendor. */
    public function index(Request $request, Vendor $vendor): AnonymousResourceCollection
    {
        $this->authorize('view', $vendor);

        $items = $this->service->list($vendor, $request->boolean('active_only'));

        return VendorItemResource::collection($items);
    }

    /** Create a single item. */
    public function store(Request $request, Vendor $vendor): VendorItemResource
    {
        $this->authorize('manage', $vendor);

        $validated = $request->validate([
            'item_code'       => ['required', 'string', 'max:100'],
            'item_name'       => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'unit_of_measure' => ['nullable', 'string', 'max:50'],
            'unit_price'      => ['required', 'integer', 'min:0'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $item = $this->service->create($vendor, $validated, auth()->user());

        return new VendorItemResource($item);
    }

    /** Update a single item. */
    public function update(Request $request, Vendor $vendor, VendorItem $vendorItem): VendorItemResource
    {
        $this->authorize('manage', $vendor);

        $validated = $request->validate([
            'item_code'       => ['sometimes', 'string', 'max:100'],
            'item_name'       => ['sometimes', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'unit_of_measure' => ['nullable', 'string', 'max:50'],
            'unit_price'      => ['sometimes', 'integer', 'min:0'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $item = $this->service->update($vendorItem, $validated);

        return new VendorItemResource($item);
    }

    /** Soft-delete a single item. */
    public function destroy(Vendor $vendor, VendorItem $vendorItem): JsonResponse
    {
        $this->authorize('manage', $vendor);

        $this->service->delete($vendorItem);

        return response()->json(['success' => true]);
    }

    /**
     * Bulk import via JSON array of rows.
     * Accepts a JSON body: { "items": [{ "item_code": "...", "item_name": "...", ... }] }
     * Also used as the target for file-parsed data from the frontend CSV import modal.
     */
    public function import(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorize('manage', $vendor);

        $request->validate([
            'items'                    => ['required', 'array', 'min:1', 'max:500'],
            'items.*.item_code'        => ['required', 'string', 'max:100'],
            'items.*.item_name'        => ['required', 'string', 'max:255'],
            'items.*.description'      => ['nullable', 'string'],
            'items.*.unit_of_measure'  => ['nullable', 'string', 'max:50'],
            'items.*.unit_price'       => ['required', 'numeric', 'min:0'],
            'items.*.is_active'        => ['nullable', 'boolean'],
        ]);

        $result = $this->service->importRows($vendor, $request->input('items'), auth()->user());

        return response()->json([
            'success' => true,
            'created' => $result['created'],
            'updated' => $result['updated'],
        ]);
    }
}
