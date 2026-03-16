<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\StockLedger;
use App\Domains\Inventory\Services\StockService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Http\Resources\Inventory\StockBalanceResource;
use App\Http\Resources\Inventory\StockLedgerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class StockController extends Controller
{
    public function __construct(private readonly StockService $service) {}

    public function balances(Request $request): ResourceCollection
    {
        $perPage = min((int) ($request->input('per_page', 25)), 100);

        $balances = StockBalance::with(['item.category', 'location'])
            ->when($request->input('item_id'), fn ($q, $v) => $q->where('item_id', $v))
            ->when($request->input('location_id'), fn ($q, $v) => $q->where('location_id', $v))
            ->when($request->boolean('low_stock'), function ($q) {
                $q->whereHas('item', fn ($qi) => $qi->whereColumn(
                    'stock_balances.quantity_on_hand', '<=', 'item_masters.reorder_point'
                ));
            })
            ->when($request->input('search'), function ($q, $search) {
                $q->whereHas('item', fn ($qi) => $qi
                    ->where('item_code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%"));
            })
            ->paginate($perPage);

        return StockBalanceResource::collection($balances);
    }

    public function ledger(Request $request): ResourceCollection
    {
        $ledger = StockLedger::with(['item', 'location', 'createdBy'])
            ->when($request->input('item_id'), fn ($q, $v) => $q->where('item_id', $v))
            ->when($request->input('location_id'), fn ($q, $v) => $q->where('location_id', $v))
            ->when($request->input('date_from'), fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->input('date_to'), fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->when($request->input('transaction_type'), fn ($q, $v) => $q->where('transaction_type', $v))
            ->orderByDesc('created_at')
            ->paginate(100);

        return StockLedgerResource::collection($ledger);
    }

    public function adjust(StockAdjustmentRequest $request): JsonResponse
    {
        $this->authorize('adjust', ItemMaster::class);

        $validated = $request->validated();
        $entry = $this->service->adjust(
            itemId: $validated['item_id'],
            locationId: $validated['location_id'],
            adjustedQty: $validated['adjusted_qty'],
            actor: $request->user(),
            remarks: $validated['remarks'],
        );

        return response()->json([
            'message' => 'Stock adjusted successfully.',
            'data' => new StockLedgerResource($entry),
        ]);
    }
}
