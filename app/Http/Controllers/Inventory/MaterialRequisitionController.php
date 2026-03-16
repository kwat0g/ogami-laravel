<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Inventory\Services\MaterialRequisitionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreMaterialRequisitionRequest;
use App\Http\Resources\Inventory\MaterialRequisitionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class MaterialRequisitionController extends Controller
{
    public function __construct(private readonly MaterialRequisitionService $service) {}

    public function index(Request $request): ResourceCollection
    {
        $this->authorize('viewAny', MaterialRequisition::class);

        $query = MaterialRequisition::with(['requestedBy', 'department', 'productionOrder'])
            ->when($request->boolean('with_archived'), fn ($q) => $q->withTrashed())
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->input('search'), fn ($q, $v) => $q->where('mr_reference', 'ilike', "%{$v}%"))
            ->orderByDesc('created_at');

        return MaterialRequisitionResource::collection($query->paginate(25));
    }

    public function store(StoreMaterialRequisitionRequest $request): MaterialRequisitionResource
    {
        $this->authorize('create', MaterialRequisition::class);
        $validated = $request->validated();
        $mrq = $this->service->store(
            data: $validated,
            items: $validated['items'],
            actor: $request->user(),
        );

        return new MaterialRequisitionResource($mrq->load(['requestedBy', 'department', 'items.item']));
    }

    public function show(MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $this->authorize('view', $materialRequisition);

        return new MaterialRequisitionResource(
            $materialRequisition->load(['requestedBy', 'department', 'items.item', 'notedBy', 'checkedBy', 'reviewedBy', 'vpApprovedBy', 'rejectedBy', 'fulfilledBy'])
        );
    }

    public function submit(Request $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $this->authorize('update', $materialRequisition);
        $reason = $request->validate(['stock_override_reason' => 'nullable|string|max:500'])['stock_override_reason'] ?? null;

        return new MaterialRequisitionResource($this->service->submit($materialRequisition, $request->user(), $reason));
    }

    public function note(Request $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $this->authorize('note', $materialRequisition);
        $comments = $request->validate(['comments' => 'nullable|string|max:1000'])['comments'] ?? null;

        return new MaterialRequisitionResource($this->service->note($materialRequisition, $request->user(), $comments));
    }

    public function check(Request $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $this->authorize('check', $materialRequisition);
        $comments = $request->validate(['comments' => 'nullable|string|max:1000'])['comments'] ?? null;

        return new MaterialRequisitionResource($this->service->check($materialRequisition, $request->user(), $comments));
    }

    public function review(Request $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $this->authorize('review', $materialRequisition);
        $comments = $request->validate(['comments' => 'nullable|string|max:1000'])['comments'] ?? null;

        return new MaterialRequisitionResource($this->service->review($materialRequisition, $request->user(), $comments));
    }

    public function vpApprove(Request $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $this->authorize('vpApprove', $materialRequisition);
        $comments = $request->validate(['comments' => 'nullable|string|max:1000'])['comments'] ?? null;

        return new MaterialRequisitionResource($this->service->vpApprove($materialRequisition, $request->user(), $comments));
    }

    public function reject(Request $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $this->authorize('reject', $materialRequisition);
        $reason = $request->validate(['reason' => 'required|string|max:1000'])['reason'];

        return new MaterialRequisitionResource($this->service->reject($materialRequisition, $request->user(), $reason));
    }

    public function cancel(Request $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $this->authorize('cancel', $materialRequisition);

        return new MaterialRequisitionResource($this->service->cancel($materialRequisition));
    }

    public function fulfill(Request $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $this->authorize('fulfill', $materialRequisition);
        $locationId = $request->validate(['location_id' => 'required|exists:warehouse_locations,id'])['location_id'];

        return new MaterialRequisitionResource($this->service->fulfill($materialRequisition, $request->user(), $locationId));
    }
}
