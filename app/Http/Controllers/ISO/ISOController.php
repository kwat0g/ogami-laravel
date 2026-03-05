<?php

declare(strict_types=1);

namespace App\Http\Controllers\ISO;

use App\Domains\ISO\Models\ControlledDocument;
use App\Domains\ISO\Models\InternalAudit;
use App\Domains\ISO\Services\ISOService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ISO\StoreControlledDocumentRequest;
use App\Http\Requests\ISO\StoreInternalAuditRequest;
use App\Http\Requests\ISO\StoreAuditFindingRequest;
use App\Http\Resources\ISO\ControlledDocumentResource;
use App\Http\Resources\ISO\InternalAuditResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ISOController extends Controller
{
    public function __construct(private readonly ISOService $service) {}

    // ── Controlled Documents ──────────────────────────────────────────────

    public function indexDocuments(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ControlledDocument::class);
        return ControlledDocumentResource::collection(
            $this->service->paginateDocuments($request->only('status', 'document_type'))
        );
    }

    public function storeDocument(StoreControlledDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', ControlledDocument::class);
        $doc = $this->service->storeDocument($request->validated(), $request->user()->id);
        return (new ControlledDocumentResource($doc))->response()->setStatusCode(201);
    }

    public function showDocument(ControlledDocument $controlledDocument): ControlledDocumentResource
    {
        $this->authorize('view', $controlledDocument);
        return new ControlledDocumentResource(
            $controlledDocument->loadMissing(['owner', 'revisions.revisedBy'])
        );
    }

    public function updateDocument(StoreControlledDocumentRequest $request, ControlledDocument $controlledDocument): ControlledDocumentResource
    {
        $this->authorize('update', $controlledDocument);
        return new ControlledDocumentResource(
            $this->service->updateDocument($controlledDocument, $request->validated())
        );
    }

    // ── Internal Audits ───────────────────────────────────────────────────

    public function indexAudits(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InternalAudit::class);
        return InternalAuditResource::collection(
            $this->service->paginateAudits($request->only('status'))
        );
    }

    public function storeAudit(StoreInternalAuditRequest $request): JsonResponse
    {
        $this->authorize('audit', InternalAudit::class);
        $audit = $this->service->storeAudit($request->validated(), $request->user()->id);
        return (new InternalAuditResource($audit))->response()->setStatusCode(201);
    }

    public function showAudit(InternalAudit $internalAudit): InternalAuditResource
    {
        $this->authorize('view', $internalAudit);
        return new InternalAuditResource(
            $internalAudit->loadMissing(['leadAuditor', 'findings.improvementActions'])
        );
    }

    public function startAudit(InternalAudit $internalAudit): InternalAuditResource
    {
        $this->authorize('audit', $internalAudit);
        return new InternalAuditResource($this->service->startAudit($internalAudit));
    }

    public function completeAudit(Request $request, InternalAudit $internalAudit): InternalAuditResource
    {
        $this->authorize('audit', $internalAudit);
        return new InternalAuditResource(
            $this->service->completeAudit($internalAudit, $request->input('summary'))
        );
    }

    public function storeFinding(StoreAuditFindingRequest $request, InternalAudit $internalAudit): JsonResponse
    {
        $this->authorize('audit', $internalAudit);
        $finding = $this->service->storeFinding($internalAudit, $request->validated(), $request->user()->id);
        return response()->json(['data' => $finding], 201);
    }
}
