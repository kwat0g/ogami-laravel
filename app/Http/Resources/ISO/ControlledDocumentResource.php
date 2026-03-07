<?php

declare(strict_types=1);

namespace App\Http\Resources\ISO;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ControlledDocumentResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'ulid'            => $this->ulid,
            'doc_code'        => $this->doc_code,
            'title'           => $this->title,
            'category'        => $this->category,
            'document_type'   => $this->document_type,
            'current_version' => $this->current_version,
            'status'          => $this->status,
            'effective_date'  => $this->effective_date?->toDateString(),
            'review_date'     => $this->review_date?->toDateString(),
            'is_active'       => $this->is_active,
            'owner'           => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id'   => $this->owner->id,
                'name' => $this->owner->name,
            ] : null),
            'revisions'       => $this->whenLoaded('revisions', fn () => $this->revisions->map(fn ($r) => [
                'id'             => $r->id,
                'version'        => $r->version,
                'change_summary' => $r->change_summary,
                'approved_at'    => $r->approved_at?->toISOString(),
                'revised_by'     => $r->relationLoaded('revisedBy') && $r->revisedBy ? [
                    'id'   => $r->revisedBy->id,
                    'name' => $r->revisedBy->name,
                ] : null,
            ])),
            'deleted_at'      => $this->deleted_at?->toISOString(),
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
