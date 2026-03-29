<?php

declare(strict_types=1);

namespace App\Http\Resources\HR\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'application_number' => $this->application_number,
            'candidate' => $this->whenLoaded('candidate', fn () => new CandidateResource($this->candidate)),
            'posting' => $this->whenLoaded('posting', fn () => [
                'ulid' => $this->posting->ulid,
                'posting_number' => $this->posting->posting_number,
                'title' => $this->posting->title,
                'requisition' => $this->posting->requisition ? [
                    'ulid' => $this->posting->requisition->ulid,
                    'requisition_number' => $this->posting->requisition->requisition_number,
                    'department' => $this->posting->requisition->department?->name,
                    'position' => $this->posting->requisition->position?->title,
                ] : null,
            ]),
            'cover_letter' => $this->cover_letter,
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'application_date' => (string) $this->application_date,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'withdrawn_reason' => $this->withdrawn_reason,
            'interviews' => $this->whenLoaded('interviews', fn () => $this->interviews->map(fn ($i) => [
                'id' => $i->id,
                'round' => $i->round,
                'type' => $i->type->value,
                'type_label' => $i->type->label(),
                'scheduled_at' => $i->scheduled_at->toIso8601String(),
                'duration_minutes' => $i->duration_minutes,
                'location' => $i->location,
                'interviewer' => ['id' => $i->interviewer->id, 'name' => $i->interviewer->name],
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'status_color' => $i->status->color(),
                'evaluation' => $i->evaluation ? [
                    'overall_score' => $i->evaluation->overall_score,
                    'recommendation' => $i->evaluation->recommendation->value,
                    'recommendation_label' => $i->evaluation->recommendation->label(),
                    'recommendation_color' => $i->evaluation->recommendation->color(),
                    'scorecard' => $i->evaluation->scorecard,
                    'general_remarks' => $i->evaluation->general_remarks,
                    'submitted_at' => $i->evaluation->submitted_at->toIso8601String(),
                ] : null,
            ])),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($d) => [
                'id' => $d->id,
                'label' => $d->label,
                'file_path' => $d->file_path,
                'mime_type' => $d->mime_type,
                'file_size' => $d->file_size,
                'created_at' => $d->created_at?->toIso8601String(),
            ])),
            'offer' => $this->whenLoaded('offer', fn () => $this->offer ? new JobOfferResource($this->offer) : null),
            'pre_employment' => $this->whenLoaded('preEmploymentChecklist', fn () => $this->preEmploymentChecklist
                ? new PreEmploymentChecklistResource($this->preEmploymentChecklist)
                : null),
            'hiring' => $this->whenLoaded('hiring', fn () => $this->hiring ? [
                'ulid' => $this->hiring->ulid,
                'status' => $this->hiring->status->value,
                'hired_at' => $this->hiring->hired_at?->toIso8601String(),
                'start_date' => (string) $this->hiring->start_date,
                'employee_id' => $this->hiring->employee_id,
            ] : null),
            'audit_trail' => $this->whenLoaded('audits', fn () => $this->audits->map(fn ($audit) => [
                'id' => $audit->id,
                'event' => $audit->event,
                'old_values' => $audit->old_values,
                'new_values' => $audit->new_values,
                'user_id' => $audit->user_id,
                'user_type' => $audit->user_type,
                'created_at' => $audit->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
