<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Services\PurchaseRequestService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePurchaseRequestRequest;
use App\Http\Requests\Procurement\UpdatePurchaseRequestRequest;
use App\Http\Resources\Procurement\PurchaseRequestResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestService $service,
    ) {}

    /**
     * List PRs — scoped by department access.
     *   ?status=draft|pending_review|reviewed|budget_verified|approved|rejected|cancelled|converted_to_po
     *   ?department_id=3
     *   ?urgency=normal|urgent|critical
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $user = auth()->user();

        // Dept heads (create-dept only) are automatically scoped to their own department(s).
        // Full-access roles (VP, Purchasing, admin) see all PRs.
        // Accounting budget verifiers must also see cross-department PRs at review stage.
        $isDeptHeadScope = $user->hasPermissionTo('procurement.purchase-request.create-dept')
            && ! $user->hasPermissionTo('procurement.purchase-request.create')
            && ! $user->hasPermissionTo('procurement.purchase-request.budget-check')
            && ! $user->hasAnyRole(['executive', 'vice_president', 'admin', 'super_admin']);

        $query = PurchaseRequest::with(['requestedBy', 'submittedBy', 'reviewedBy', 'department', 'items'])
            ->when($request->boolean('with_archived'), fn ($q) => $q->withTrashed())
            ->when(
                $isDeptHeadScope,
                fn ($q) => $q->whereIn('department_id', $user->departments()->pluck('departments.id')),
            )
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->input('status')),
            )
            ->when(
                $request->filled('urgency'),
                fn ($q) => $q->where('urgency', $request->input('urgency')),
            )
            ->when(
                $request->filled('department_id'),
                fn ($q) => $q->where('department_id', $request->integer('department_id')),
            )
            ->orderByDesc('created_at');

        return PurchaseRequestResource::collection($query->paginate(25));
    }

    public function store(StorePurchaseRequestRequest $request): PurchaseRequestResource
    {
        $validated = $request->validated();
        $actor = auth()->user();

        // Check general create permission
        $this->authorize('create', PurchaseRequest::class);

        // Additional check: department heads can only create for their own department
        $this->authorize('createForDepartment', [
            PurchaseRequest::class,
            $validated['department_id'],
        ]);

        $pr = $this->service->store(
            data: $validated,
            items: $validated['items'],
            actor: $actor,
        );

        return new PurchaseRequestResource($pr->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items',
        ]));
    }

    /**
     * Create a Purchase Request from an approved Material Requisition.
     * Used when stock is insufficient to fulfill the MRQ internally.
     */
    public function createFromMrq(Request $request, MaterialRequisition $materialRequisition): PurchaseRequestResource
    {
        $this->authorize('createFromMrq', [PurchaseRequest::class, $materialRequisition]);

        $validated = $request->validate([
            'justification' => ['nullable', 'string', 'max:1000'],
        ]);

        $pr = $this->service->createFromMrq(
            mrq: $materialRequisition,
            actor: auth()->user(),
            justification: $validated['justification'] ?? null,
        );

        return new PurchaseRequestResource($pr->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items', 'sourceMrq',
        ]));
    }

    public function show(PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('view', $purchaseRequest);

        return new PurchaseRequestResource(
            $purchaseRequest->load([
                'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
                'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items', 'department',
            ])
        );
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('update', $purchaseRequest);

        $validated = $request->validated();
        $pr = $this->service->update(
            pr: $purchaseRequest,
            data: $validated,
            items: $validated['items'] ?? [],
        );

        return new PurchaseRequestResource($pr->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items',
        ]));
    }

    // ── Workflow Actions ─────────────────────────────────────────────────────

    public function submit(PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('submit', $purchaseRequest);

        $pr = $this->service->submit($purchaseRequest, auth()->user());

        return new PurchaseRequestResource($pr->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items',
        ]));
    }

    /** Purchasing Department reviews PR for technical validity (pending_review → reviewed) */
    public function review(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('review', $purchaseRequest);

        $pr = $this->service->review(
            $purchaseRequest,
            auth()->user(),
            (string) $request->input('comments', ''),
        );

        return new PurchaseRequestResource($pr->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items',
        ]));
    }

    /** Accounting verifies budget commitment */
    public function budgetCheck(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('budgetCheck', $purchaseRequest);

        $pr = $this->service->budgetCheck(
            $purchaseRequest,
            auth()->user(),
            (string) $request->input('comments', ''),
        );

        return new PurchaseRequestResource($pr->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items',
        ]));
    }

    /** VP gives final approval */
    public function vpApprove(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('vpApprove', $purchaseRequest);

        $pr = $this->service->vpApprove(
            $purchaseRequest,
            auth()->user(),
            (string) $request->input('comments', ''),
        );

        return new PurchaseRequestResource($pr->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items',
        ]));
    }

    /** Accounting Officer returns the PR for revision */
    public function returnForRevision(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('returnForRevision', $purchaseRequest);

        $request->validate([
            'reason' => ['required', 'string', 'min:10'],
        ]);

        $pr = $this->service->returnForRevision(
            $purchaseRequest,
            auth()->user(),
            $request->string('reason')->value(),
        );

        return new PurchaseRequestResource($pr->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items',
        ]));
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('reject', $purchaseRequest);

        $request->validate([
            'reason' => ['required', 'string', 'min:10'],
            'stage' => ['required', 'string'],
        ]);

        $pr = $this->service->reject(
            $purchaseRequest,
            auth()->user(),
            $request->string('reason')->value(),
            $request->string('stage')->value(),
        );

        return new PurchaseRequestResource($pr->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items',
        ]));
    }

    /**
     * POST /api/v1/procurement/purchase-requests/batch-review
     * Batch review multiple pending_review PRs.
     */
    public function batchReview(Request $request): JsonResponse
    {
        $request->validate([
            'ids'      => ['required', 'array', 'min:1', 'max:50'],
            'ids.*'    => ['integer', 'exists:purchase_requests,id'],
            'comments' => ['nullable', 'string', 'max:500'],
        ]);

        $user     = auth()->user();
        $comments = (string) $request->input('comments', '');
        $results  = ['reviewed' => [], 'failed' => []];

        foreach ($request->input('ids') as $id) {
            try {
                $pr = PurchaseRequest::findOrFail($id);
                $this->authorize('review', $pr);
                $this->service->review($pr, $user, $comments);
                $results['reviewed'][] = $id;
            } catch (\Throwable $e) {
                $results['failed'][] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => count($results['reviewed']) . ' PR(s) reviewed.',
            'results' => $results,
        ]);
    }

    /**
     * POST /api/v1/procurement/purchase-requests/batch-reject
     * Batch reject multiple PRs.
     */
    public function batchReject(Request $request): JsonResponse
    {
        $request->validate([
            'ids'    => ['required', 'array', 'min:1', 'max:50'],
            'ids.*'  => ['integer', 'exists:purchase_requests,id'],
            'reason' => ['required', 'string', 'min:10'],
            'stage'  => ['required', 'string'],
        ]);

        $user    = auth()->user();
        $reason  = $request->string('reason')->value();
        $stage   = $request->string('stage')->value();
        $results = ['rejected' => [], 'failed' => []];

        foreach ($request->input('ids') as $id) {
            try {
                $pr = PurchaseRequest::findOrFail($id);
                $this->authorize('reject', $pr);
                $this->service->reject($pr, $user, $reason, $stage);
                $results['rejected'][] = $id;
            } catch (\Throwable $e) {
                $results['failed'][] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => count($results['rejected']) . ' PR(s) rejected.',
            'results' => $results,
        ]);
    }

    public function cancel(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('cancel', $purchaseRequest);

        $reason = $request->input('cancellation_reason', '');

        $this->service->cancel($purchaseRequest, auth()->user(), $reason);

        return response()->json(['success' => true, 'message' => 'Purchase Request cancelled.']);
    }

    /** Export PR as PDF (opens inline in browser). */
    public function pdf(PurchaseRequest $purchaseRequest): Response
    {
        $this->authorize('view', $purchaseRequest);

        $pr = $purchaseRequest->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'department', 'items',
        ]);

        $settings = [
            'company_name' => config('app.company_name', 'Ogami Manufacturing Corp.'),
            'company_address' => config('app.company_address', ''),
        ];

        $pdf = Pdf::loadView('procurement.purchase-request-pdf', compact('pr', 'settings'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("PR-{$pr->pr_reference}.pdf");
    }

    /** Duplicate an existing PR with a new reference number. */
    public function duplicate(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('create', PurchaseRequest::class);

        $pr = $this->service->duplicate($purchaseRequest->id, auth()->user());

        return new PurchaseRequestResource($pr->load([
            'requestedBy', 'submittedBy', 'reviewedBy', 'budgetCheckedBy',
            'returnedBy', 'vpApprovedBy', 'rejectedBy', 'items',
        ]));
    }
}
