<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

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
     *   ?status=draft|submitted|noted|checked|reviewed|approved|rejected|cancelled
     *   ?department_id=3
     *   ?urgency=normal|urgent|critical
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $user  = auth()->user();
        $query = PurchaseRequest::with(['requestedBy', 'items'])
            ->when($request->boolean('with_archived'), fn ($q) => $q->withTrashed())
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
        $this->authorize('create', PurchaseRequest::class);

        $validated = $request->validated();
        $pr = $this->service->store(
            data:  $validated,
            items: $validated['items'],
            actor: auth()->user(),
        );

        return new PurchaseRequestResource($pr->load(['requestedBy', 'items']));
    }

    public function show(PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('view', $purchaseRequest);

        return new PurchaseRequestResource(
            $purchaseRequest->load([
                'requestedBy', 'submittedBy', 'notedBy', 'checkedBy',
                'reviewedBy', 'budgetCheckedBy', 'returnedBy',
                'vpApprovedBy', 'rejectedBy', 'items',
            ])
        );
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('update', $purchaseRequest);

        $validated = $request->validated();
        $pr = $this->service->update(
            pr:    $purchaseRequest,
            data:  $validated,
            items: $validated['items'] ?? [],
        );

        return new PurchaseRequestResource($pr->load(['requestedBy', 'items']));
    }

    // ── Workflow Actions ─────────────────────────────────────────────────────

    public function submit(PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('submit', $purchaseRequest);

        $pr = $this->service->submit($purchaseRequest, auth()->user());

        return new PurchaseRequestResource($pr->load(['requestedBy', 'submittedBy', 'items']));
    }

    /** HEAD notes the PR (SOD-011) */
    public function note(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('note', $purchaseRequest);

        $pr = $this->service->note(
            $purchaseRequest,
            auth()->user(),
            (string) $request->input('comments', ''),
        );

        return new PurchaseRequestResource($pr->load(['notedBy', 'items']));
    }

    /** MANAGER checks the PR (SOD-012) */
    public function check(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('check', $purchaseRequest);

        $pr = $this->service->check(
            $purchaseRequest,
            auth()->user(),
            (string) $request->input('comments', ''),
        );

        return new PurchaseRequestResource($pr->load(['checkedBy', 'items']));
    }

    /** OFFICER reviews the PR (SOD-013) */
    public function review(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('review', $purchaseRequest);

        $pr = $this->service->review(
            $purchaseRequest,
            auth()->user(),
            (string) $request->input('comments', ''),
        );

        return new PurchaseRequestResource($pr->load(['reviewedBy', 'items']));
    }

    /** VP gives final approval (SOD-014) */
    public function vpApprove(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('vpApprove', $purchaseRequest);

        $pr = $this->service->vpApprove(
            $purchaseRequest,
            auth()->user(),
            (string) $request->input('comments', ''),
        );

        return new PurchaseRequestResource($pr->load(['vpApprovedBy', 'items']));
    }

    /** Accounting Officer passes budget check — moves PR to budget_checked */
    public function budgetCheck(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('budgetCheck', $purchaseRequest);

        $pr = $this->service->budgetCheck(
            $purchaseRequest,
            auth()->user(),
            (string) $request->input('comments', ''),
        );

        return new PurchaseRequestResource($pr->load(['budgetCheckedBy', 'items']));
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

        return new PurchaseRequestResource($pr->load(['returnedBy', 'items']));
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('reject', $purchaseRequest);

        $request->validate([
            'reason' => ['required', 'string', 'min:10'],
            'stage'  => ['required', 'string'],
        ]);

        $pr = $this->service->reject(
            $purchaseRequest,
            auth()->user(),
            $request->string('reason')->value(),
            $request->string('stage')->value(),
        );

        return new PurchaseRequestResource($pr->load(['rejectedBy', 'items']));
    }

    public function cancel(PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('cancel', $purchaseRequest);

        $this->service->cancel($purchaseRequest, auth()->user());

        return response()->json(['success' => true, 'message' => 'Purchase Request cancelled.']);
    }

    /** Export PR as PDF (opens inline in browser). */
    public function pdf(PurchaseRequest $purchaseRequest): Response
    {
        $this->authorize('view', $purchaseRequest);

        $pr = $purchaseRequest->load([
            'requestedBy', 'submittedBy', 'notedBy', 'checkedBy',
            'reviewedBy', 'budgetCheckedBy', 'returnedBy',
            'vpApprovedBy', 'rejectedBy', 'department', 'items',
        ]);

        $settings = [
            'company_name'    => config('app.company_name', 'Ogami Manufacturing Corp.'),
            'company_address' => config('app.company_address', ''),
        ];

        $pdf = Pdf::loadView('procurement.purchase-request-pdf', compact('pr', 'settings'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("PR-{$pr->pr_reference}.pdf");
    }
}
