<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Domains\Inventory\Models\PhysicalCount;
use App\Domains\Inventory\Services\PhysicalCountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PhysicalCountController extends Controller
{
    public function __construct(private readonly PhysicalCountService $service) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->service->paginate($request->only(['status', 'location_id', 'per_page']));

        return response()->json($page);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:warehouse_locations,id'],
            'count_date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'string'],
            'item_ids' => ['sometimes', 'array'],
            'item_ids.*' => ['integer', 'exists:item_masters,id'],
        ]);

        $count = $this->service->store($data, $request->user());

        return response()->json(['data' => $count], 201);
    }

    public function show(PhysicalCount $physicalCount): JsonResponse
    {
        return response()->json([
            'data' => $physicalCount->load('items.item', 'location', 'createdBy', 'approvedBy'),
        ]);
    }

    public function startCounting(PhysicalCount $physicalCount): JsonResponse
    {
        return response()->json([
            'data' => $this->service->startCounting($physicalCount),
        ]);
    }

    public function recordCounts(Request $request, PhysicalCount $physicalCount): JsonResponse
    {
        $data = $request->validate([
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.item_id' => ['required', 'integer'],
            'counts.*.counted_qty' => ['required', 'numeric', 'min:0'],
            'counts.*.remarks' => ['sometimes', 'string'],
        ]);

        return response()->json([
            'data' => $this->service->recordCounts($physicalCount, $data['counts']),
        ]);
    }

    public function submitForApproval(PhysicalCount $physicalCount): JsonResponse
    {
        return response()->json([
            'data' => $this->service->submitForApproval($physicalCount),
        ]);
    }

    public function approve(Request $request, PhysicalCount $physicalCount): JsonResponse
    {
        return response()->json([
            'data' => $this->service->approve($physicalCount, $request->user()),
        ]);
    }
}
