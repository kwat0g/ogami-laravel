<?php

declare(strict_types=1);

namespace App\Http\Controllers\FixedAssets;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\FixedAssets\Models\FixedAsset;
use App\Domains\FixedAssets\Models\FixedAssetCategory;
use App\Domains\FixedAssets\Services\FixedAssetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\FixedAssets\DepreciatePeriodRequest;
use App\Http\Requests\FixedAssets\DisposeAssetRequest;
use App\Http\Requests\FixedAssets\StoreFixedAssetCategoryRequest;
use App\Http\Requests\FixedAssets\StoreFixedAssetRequest;
use App\Http\Requests\FixedAssets\UpdateFixedAssetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FixedAssetController extends Controller
{
    public function __construct(private readonly FixedAssetService $service) {}

    // ── Categories ───────────────────────────────────────────────────────────

    public function indexCategories(): JsonResponse
    {
        $this->authorize('viewAny', FixedAsset::class);

        return response()->json(FixedAssetCategory::orderBy('name')->get());
    }

    public function storeCategory(StoreFixedAssetCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', FixedAsset::class);

        $data = $request->validated();

        $category = $this->service->storeCategory($data, $request->user());

        return response()->json(['data' => $category], 201);
    }

    // ── Asset Register ───────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FixedAsset::class);

        $assets = FixedAsset::with('category', 'department')
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->orderByDesc('acquisition_date')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json($assets);
    }

    public function store(StoreFixedAssetRequest $request): JsonResponse
    {
        $this->authorize('create', FixedAsset::class);

        $data = $request->validated();

        $asset = $this->service->register($data, $request->user());

        return response()->json(['data' => $asset->load('category', 'department')], 201);
    }

    public function show(FixedAsset $fixedAsset): JsonResponse
    {
        $this->authorize('view', $fixedAsset);

        return response()->json([
            'data' => $fixedAsset->load('category', 'department', 'depreciationEntries.fiscalPeriod', 'disposal'),
        ]);
    }

    public function update(UpdateFixedAssetRequest $request, FixedAsset $fixedAsset): JsonResponse
    {
        $this->authorize('update', $fixedAsset);

        if ($fixedAsset->status === 'disposed') {
            return response()->json(['message' => 'Disposed assets cannot be updated.'], 422);
        }

        $data = $request->validated();

        $fixedAsset->update($data);

        return response()->json(['data' => $fixedAsset->refresh()]);
    }

    // ── Depreciation ─────────────────────────────────────────────────────────

    public function depreciatePeriod(DepreciatePeriodRequest $request): JsonResponse
    {
        $this->authorize('depreciate', FixedAsset::class);

        $data = $request->validated();

        /** @var FiscalPeriod $period */
        $period = FiscalPeriod::findOrFail($data['fiscal_period_id']);

        $count = $this->service->depreciateMonth($period, $request->user());

        return response()->json([
            'message' => "Deprecated {$count} asset(s) for period {$period->name}.",
            'count'   => $count,
        ]);
    }

    // ── Disposal ─────────────────────────────────────────────────────────────

    public function dispose(DisposeAssetRequest $request, FixedAsset $fixedAsset): JsonResponse
    {
        $this->authorize('dispose', $fixedAsset);

        $data = $request->validated();

        $disposal = $this->service->dispose($fixedAsset, $data, $request->user());

        return response()->json(['data' => $disposal], 201);
    }
}
