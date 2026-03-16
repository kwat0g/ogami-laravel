<?php

declare(strict_types=1);

namespace App\Http\Resources\ISO;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InternalAuditResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'audit_reference' => $this->audit_reference,
            'audit_scope' => $this->audit_scope,
            'standard' => $this->standard,
            'audit_date' => $this->audit_date?->toDateString(),
            'status' => $this->status,
            'summary' => $this->summary,
            'closed_at' => $this->closed_at?->toISOString(),
            'lead_auditor' => $this->whenLoaded('leadAuditor', fn () => $this->leadAuditor ? [
                'id' => $this->leadAuditor->id,
                'name' => $this->leadAuditor->name,
            ] : null),
            'findings' => $this->whenLoaded('findings', fn () => $this->findings->map(fn ($f) => [
                'id' => $f->id,
                'ulid' => $f->ulid,
                'finding_type' => $f->finding_type,
                'clause_ref' => $f->clause_ref,
                'description' => $f->description,
                'severity' => $f->severity,
                'status' => $f->status,
                'actions_count' => $f->relationLoaded('improvementActions') ? $f->improvementActions->count() : null,
            ])),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
