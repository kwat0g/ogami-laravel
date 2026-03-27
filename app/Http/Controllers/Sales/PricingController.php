<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Domains\Sales\Services\PricingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PricingController extends Controller
{
    public function __construct(private readonly PricingService $service) {}

    /**
     * Get resolved price for an item.
     */
    public function getPrice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'quantity' => ['sometimes', 'numeric', 'min:0.0001'],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
        ]);

        $price = $this->service->getPrice(
            itemId: $data['item_id'],
            quantity: (float) ($data['quantity'] ?? 1.0),
            customerId: $data['customer_id'] ?? null,
        );

        return response()->json(['data' => $price]);
    }
}
