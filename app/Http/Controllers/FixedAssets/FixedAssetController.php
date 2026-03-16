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
use App\Http\Resources\FixedAssets\FixedAssetCategoryResource;
use App\Http\Resources\FixedAssets\FixedAssetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class FixedAssetController extends Controller
{
    public function __construct(private readonly FixedAssetService $service) {}

    // ── Categories ───────────────────────────────────────────────────────────

    public function indexCategories(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FixedAsset::class);

        return FixedAssetCategoryResource::collection(FixedAssetCategory::orderBy('name')->get());
    }

    public function storeCategory(StoreFixedAssetCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', FixedAsset::class);

        $data = $request->validated();

        $category = $this->service->storeCategory($data, $request->user());

        return (new FixedAssetCategoryResource($category))->response()->setStatusCode(201);
    }

    // ── Asset Register ───────────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FixedAsset::class);

        $assets = FixedAsset::with('category', 'department')
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->orderByDesc('acquisition_date')
            ->paginate((int) $request->input('per_page', 20));

        return FixedAssetResource::collection($assets);
    }

    public function store(StoreFixedAssetRequest $request): JsonResponse
    {
        $this->authorize('create', FixedAsset::class);

        $data = $request->validated();

        $asset = $this->service->register($data, $request->user());

        return (new FixedAssetResource($asset->load('category', 'department')))->response()->setStatusCode(201);
    }

    public function show(FixedAsset $fixedAsset): FixedAssetResource
    {
        $this->authorize('view', $fixedAsset);

        return new FixedAssetResource($fixedAsset->load('category', 'department', 'depreciationEntries.fiscalPeriod', 'disposal'));
    }

    public function update(UpdateFixedAssetRequest $request, FixedAsset $fixedAsset): FixedAssetResource
    {
        $this->authorize('update', $fixedAsset);

        if ($fixedAsset->status === 'disposed') {
            abort(422, 'Disposed assets cannot be updated.');
        }

        $data = $request->validated();

        $fixedAsset->update($data);

        return new FixedAssetResource($fixedAsset->refresh());
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
            'count' => $count,
        ]);
    }

    // ── Disposal ─────────────────────────────────────────────────────────────

    public function dispose(DisposeAssetRequest $request, FixedAsset $fixedAsset): JsonResponse
    {
        $this->authorize('dispose', $fixedAsset);

        $data = $request->validated();

        $disposal = $this->service->dispose($fixedAsset, $data, $request->user());

        return response()->json([
            'data' => [
                'id' => $disposal->id,
                'disposal_date' => $disposal->disposal_date?->toDateString(),
                'disposal_amount_centavos' => $disposal->disposal_amount_centavos,
                'disposal_amount' => $disposal->disposal_amount_centavos / 100,
                'gain_loss_centavos' => $disposal->gain_loss_centavos,
                'gain_loss' => $disposal->gain_loss_centavos / 100,
            ],
        ], 201);
    }
}
