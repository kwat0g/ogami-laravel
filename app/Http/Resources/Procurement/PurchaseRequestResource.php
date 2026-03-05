<?php

declare(strict_types=1);

namespace App\Http\Resources\Procurement;

use App\Domains\Procurement\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PurchaseRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var PurchaseRequest $pr */
        $pr = $this->resource;

        return [
            'id'                    => $pr->id,
            'ulid'                  => $pr->ulid,
            'pr_reference'          => $pr->pr_reference,
            'department_id'         => $pr->department_id,
            'urgency'               => $pr->urgency,
            'justification'         => $pr->justification,
            'notes'                 => $pr->notes,
            'status'                => $pr->status,
            'total_estimated_cost'  => (float) $pr->total_estimated_cost,

            // Actors
            'requested_by_id'   => $pr->requested_by_id,
            'requested_by'      => $this->whenLoaded('requestedBy', fn () => [
                'id'   => $pr->requestedBy->id,
                'name' => $pr->requestedBy->name,
            ]),

            'submitted_by_id'   => $pr->submitted_by_id,
            'submitted_at'      => $pr->submitted_at?->toIso8601String(),
            'submitted_by'      => $this->whenLoaded('submittedBy', fn () => $pr->submittedBy ? [
                'id' => $pr->submittedBy->id, 'name' => $pr->submittedBy->name,
            ] : null),

            'noted_by_id'       => $pr->noted_by_id,
            'noted_at'          => $pr->noted_at?->toIso8601String(),
            'noted_comments'    => $pr->noted_comments,
            'noted_by'          => $this->whenLoaded('notedBy', fn () => $pr->notedBy ? [
                'id' => $pr->notedBy->id, 'name' => $pr->notedBy->name,
            ] : null),

            'checked_by_id'     => $pr->checked_by_id,
            'checked_at'        => $pr->checked_at?->toIso8601String(),
            'checked_comments'  => $pr->checked_comments,
            'checked_by'        => $this->whenLoaded('checkedBy', fn () => $pr->checkedBy ? [
                'id' => $pr->checkedBy->id, 'name' => $pr->checkedBy->name,
            ] : null),

            'reviewed_by_id'      => $pr->reviewed_by_id,
            'reviewed_at'         => $pr->reviewed_at?->toIso8601String(),
            'reviewed_comments'   => $pr->reviewed_comments,
            'reviewed_by'         => $this->whenLoaded('reviewedBy', fn () => $pr->reviewedBy ? [
                'id' => $pr->reviewedBy->id, 'name' => $pr->reviewedBy->name,
            ] : null),

            'vp_approved_by_id'  => $pr->vp_approved_by_id,
            'vp_approved_at'     => $pr->vp_approved_at?->toIso8601String(),
            'vp_comments'        => $pr->vp_comments,
            'vp_approved_by'     => $this->whenLoaded('vpApprovedBy', fn () => $pr->vpApprovedBy ? [
                'id' => $pr->vpApprovedBy->id, 'name' => $pr->vpApprovedBy->name,
            ] : null),

            'rejected_by_id'     => $pr->rejected_by_id,
            'rejected_at'        => $pr->rejected_at?->toIso8601String(),
            'rejection_reason'   => $pr->rejection_reason,
            'rejection_stage'    => $pr->rejection_stage,

            'converted_to_po_id' => $pr->converted_to_po_id,
            'converted_at'       => $pr->converted_at?->toIso8601String(),

            'items'              => PurchaseRequestItemResource::collection(
                $this->whenLoaded('items')
            ),

            'created_at'  => $pr->created_at?->toIso8601String(),
            'updated_at'  => $pr->updated_at?->toIso8601String(),
        ];
    }
}
