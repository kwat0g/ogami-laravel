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
            $this->service->paginate($request->only(['status', 'severity', 'per_page', 'with_archived']))
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

    public function capaIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NonConformanceReport::class);
        $query = CapaAction::with(['ncr', 'auditFinding.audit', 'assignedTo', 'createdBy'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at');
        $paginated = $query->paginate((int) ($request->input('per_page', 20)));
        $paginated->getCollection()->transform(fn (CapaAction $c) => [
            'id'                => $c->id,
            'ulid'              => $c->ulid,
            'type'              => $c->type,
            'description'       => $c->description,
            'due_date'          => $c->due_date?->toDateString(),
            'status'            => $c->status,
            'completed_at'      => $c->completed_at?->toIso8601String(),
            'ncr_id'            => $c->ncr_id,
            'audit_finding_id'  => $c->audit_finding_id,
            'ncr_reference'     => $c->ncr?->ncr_reference,
            'audit_reference'   => $c->auditFinding?->audit?->audit_reference,
            'assigned_to'       => $c->assignedTo ? ['id' => $c->assignedTo->id, 'name' => $c->assignedTo->name] : null,
        ]);
        return response()->json($paginated);
    }

    public function completeCapa(Request $request, CapaAction $capaAction): JsonResponse
    {
        if ($capaAction->ncr_id !== null) {
            $this->authorize('issueCapa', $capaAction->ncr);
        } elseif (! $request->user()?->can('qc.ncr.create')) {
            abort(403, 'Unauthorized to complete CAPA.');
        }
        $capa = $this->service->completeCapaAction($capaAction);
        return response()->json(['data' => $capa]);
    }
}
