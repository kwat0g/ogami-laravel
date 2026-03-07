<?php

declare(strict_types=1);

namespace App\Http\Controllers\QC;

use App\Domains\QC\Models\Inspection;
use App\Domains\QC\Services\InspectionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\QC\RecordInspectionResultsRequest;
use App\Http\Requests\QC\StoreInspectionRequest;
use App\Http\Resources\QC\InspectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InspectionController extends Controller
{
    public function __construct(private readonly InspectionService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Inspection::class);
        return InspectionResource::collection(
            $this->service->paginate($request->only(['stage', 'status', 'item_master_id', 'per_page', 'with_archived']))
        );
    }

    public function store(StoreInspectionRequest $request): JsonResponse
    {
        $this->authorize('create', Inspection::class);
        $inspection = $this->service->store($request->validated(), $request->user()->id);
        return (new InspectionResource($inspection))->response()->setStatusCode(201);
    }

    public function show(Inspection $inspection): InspectionResource
    {
        $this->authorize('view', $inspection);
        return new InspectionResource($inspection->load(['template.items', 'results', 'itemMaster', 'inspector', 'ncrs']));
    }

    public function recordResults(RecordInspectionResultsRequest $request, Inspection $inspection): InspectionResource
    {
        $this->authorize('recordResults', $inspection);
        $data = $request->validated();
        $updated = $this->service->recordResults(
            $inspection,
            $data['results'],
            (int) $data['qty_passed'],
            (int) $data['qty_failed'],
        );
        return new InspectionResource($updated);
    }

    public function destroy(Inspection $inspection): \Illuminate\Http\Response
    {
        $this->authorize('delete', $inspection);
        $this->service->dismiss($inspection);
        return response()->noContent();
    }

    public function cancelResults(Request $request, Inspection $inspection): InspectionResource
    {
        $this->authorize('cancelResults', $inspection);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $updated = $this->service->cancelResults($inspection, $validated['reason']);
        return new InspectionResource($updated);
    }
}
