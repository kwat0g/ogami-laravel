<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use App\Domains\Inventory\Models\MaterialRequisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaterialRequisition */
final class MaterialRequisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mrq = $this->resource;

        return [
            'id' => $mrq->id,
            'ulid' => $mrq->ulid,
            'mr_reference' => $mrq->mr_reference,
            'department_id' => $mrq->department_id,
            'purpose' => $mrq->purpose,
            'remarks' => $mrq->remarks,
            'status' => $mrq->status,
            'is_cancellable' => $mrq->isCancellable(),
            'is_convertible_to_pr' => $mrq->isConvertibleToPr(),
            'converted_to_pr' => $mrq->converted_to_pr,
            'converted_pr_id' => $mrq->converted_pr_id,
            // SoD actor fields
            'submitted_at' => $mrq->submitted_at?->toIso8601String(),
            'noted_at' => $mrq->noted_at?->toIso8601String(),
            'noted_comments' => $mrq->noted_comments,
            'checked_at' => $mrq->checked_at?->toIso8601String(),
            'checked_comments' => $mrq->checked_comments,
            'reviewed_at' => $mrq->reviewed_at?->toIso8601String(),
            'reviewed_comments' => $mrq->reviewed_comments,
            'vp_approved_at' => $mrq->vp_approved_at?->toIso8601String(),
            'vp_comments' => $mrq->vp_comments,
            'rejected_at' => $mrq->rejected_at?->toIso8601String(),
            'rejection_reason' => $mrq->rejection_reason,
            'fulfilled_at' => $mrq->fulfilled_at?->toIso8601String(),
            'deleted_at' => $mrq->deleted_at?->toIso8601String(),
            'created_at' => $mrq->created_at?->toIso8601String(),
            // Relations
            'requested_by' => $this->whenLoaded('requestedBy', fn () => ['id' => $mrq->requestedBy->id, 'name' => $mrq->requestedBy->name]),
            'department' => $this->whenLoaded('department', fn () => ['id' => $mrq->department->id, 'name' => $mrq->department->name]),
            'production_order' => $this->whenLoaded('productionOrder', fn () => $mrq->productionOrder ? [
                'id' => $mrq->productionOrder->id,
                'ulid' => $mrq->productionOrder->ulid,
                'po_reference' => $mrq->productionOrder->po_reference,
            ] : null),
            'noted_by' => $this->whenLoaded('notedBy', fn () => $mrq->notedBy ? ['id' => $mrq->notedBy->id, 'name' => $mrq->notedBy->name] : null),
            'checked_by' => $this->whenLoaded('checkedBy', fn () => $mrq->checkedBy ? ['id' => $mrq->checkedBy->id, 'name' => $mrq->checkedBy->name] : null),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $mrq->reviewedBy ? ['id' => $mrq->reviewedBy->id, 'name' => $mrq->reviewedBy->name] : null),
            'vp_approved_by' => $this->whenLoaded('vpApprovedBy', fn () => $mrq->vpApprovedBy ? ['id' => $mrq->vpApprovedBy->id, 'name' => $mrq->vpApprovedBy->name] : null),
            'rejected_by' => $this->whenLoaded('rejectedBy', fn () => $mrq->rejectedBy ? ['id' => $mrq->rejectedBy->id, 'name' => $mrq->rejectedBy->name] : null),
            'fulfilled_by' => $this->whenLoaded('fulfilledBy', fn () => $mrq->fulfilledBy ? ['id' => $mrq->fulfilledBy->id, 'name' => $mrq->fulfilledBy->name] : null),
            'items' => $this->whenLoaded('items', fn () => $mrq->items->map(fn ($line) => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item' => $line->relationLoaded('item') ? [
                    'id' => $line->item->id,
                    'item_code' => $line->item->item_code,
                    'name' => $line->item->name,
                    'unit_of_measure' => $line->item->unit_of_measure,
                ] : null,
                'qty_requested' => $line->qty_requested,
                'qty_issued' => $line->qty_issued,
                'remarks' => $line->remarks,
                'line_order' => $line->line_order,
            ])
            ),
        ];
    }
}
