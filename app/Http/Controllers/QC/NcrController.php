<?php

declare(strict_types=1);

namespace App\Http\Controllers\QC;

use App\Domains\QC\Models\CapaAction;
use App\Domains\QC\Models\NonConformanceReport;
use App\Domains\QC\Services\NcrService;
use App\Http\Controllers\Controller;
use App\Http\Requests\QC\IssueCapaRequest;
use App\Http\Requests\QC\StoreNcrRequest;
use App\Http\Resources\QC\NcrResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class NcrController extends Controller
{
    public function __construct(private readonly NcrService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', NonConformanceReport::class);
        return NcrResource::collection(
            $this->service->paginate($request->only(['status', 'severity', 'per_page']))
        );
    }

    public function store(StoreNcrRequest $request): JsonResponse
    {
        $this->authorize('create', NonConformanceReport::class);
        $ncr = $this->service->store($request->validated(), $request->user()->id);
        return (new NcrResource($ncr))->response()->setStatusCode(201);
    }

    public function show(NonConformanceReport $nonConformanceReport): NcrResource
    {
        $this->authorize('view', $nonConformanceReport);
        return new NcrResource($nonConformanceReport->load(['inspection.itemMaster', 'raisedBy', 'capaActions.assignedTo']));
    }

    public function issueCapa(IssueCapaRequest $request, NonConformanceReport $nonConformanceReport): JsonResponse
    {
        $this->authorize('issueCapa', $nonConformanceReport);
        $capa = $this->service->issueCapa($nonConformanceReport, $request->validated(), $request->user()->id);
        return response()->json(['data' => $capa], 201);
    }

    public function close(Request $request, NonConformanceReport $nonConformanceReport): NcrResource
    {
        $this->authorize('close', $nonConformanceReport);
        $ncr = $this->service->closeNcr($nonConformanceReport, $request->user()->id);
        return new NcrResource($ncr);
    }

    public function completeCapa(CapaAction $capaAction): JsonResponse
    {
        $this->authorize('issueCapa', $capaAction->ncr);
        $capa = $this->service->completeCapaAction($capaAction);
        return response()->json(['data' => $capa]);
    }
}
