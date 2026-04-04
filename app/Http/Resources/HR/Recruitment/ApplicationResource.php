<?php

declare(strict_types=1);

namespace App\Http\Resources\HR\Recruitment;

use App\Domains\HR\Recruitment\Enums\ApplicationStatus;
use App\Domains\HR\Recruitment\Enums\InterviewStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $postingDepartment = $this->posting?->requisition?->department ?? $this->posting?->department;
        $postingPosition = $this->posting?->requisition?->position ?? $this->posting?->position;
        $hasCompletedInterview = $this->relationLoaded('interviews')
            && $this->interviews->contains(fn ($interview) => $interview->status === InterviewStatus::Completed);

        $displayStatus = $this->status->value;
        $displayStatusLabel = $this->status->label();
        $displayStatusColor = $this->status->color();

        if ($this->status === ApplicationStatus::Shortlisted && $hasCompletedInterview) {
            $displayStatus = 'interviewed';
            $displayStatusLabel = 'Interviewed';
            $displayStatusColor = 'blue';
        }

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'application_number' => $this->application_number,
            'candidate' => $this->whenLoaded('candidate', fn () => new CandidateResource($this->candidate)),
            'posting' => $this->whenLoaded('posting', fn () => [
                'id' => $this->posting->id,
                'ulid' => $this->posting->ulid,
                'posting_number' => $this->posting->posting_number,
                'title' => $this->posting->title,
                'salary_grade_id' => $this->posting->salary_grade_id ?? $this->posting->requisition?->salary_grade_id,
                'salary_grade' => ($this->posting->salaryGrade ?? $this->posting->requisition?->salaryGrade) ? [
                    'id' => ($this->posting->salaryGrade ?? $this->posting->requisition?->salaryGrade)?->id,
                    'code' => ($this->posting->salaryGrade ?? $this->posting->requisition?->salaryGrade)?->code,
                    'name' => ($this->posting->salaryGrade ?? $this->posting->requisition?->salaryGrade)?->name,
                    'level' => ($this->posting->salaryGrade ?? $this->posting->requisition?->salaryGrade)?->level,
                    'min_monthly_rate' => ($this->posting->salaryGrade ?? $this->posting->requisition?->salaryGrade)?->min_monthly_rate,
                    'max_monthly_rate' => ($this->posting->salaryGrade ?? $this->posting->requisition?->salaryGrade)?->max_monthly_rate,
                ] : null,
                'requisition' => [
                    'ulid' => $this->posting->requisition?->ulid,
                    'requisition_number' => $this->posting->requisition?->requisition_number,
                    'department' => $postingDepartment?->name,
                    'position' => $postingPosition?->title,
                ],
                'department' => $postingDepartment?->name,
                'position' => $postingPosition?->title,
            ]),
            'cover_letter' => $this->cover_letter,
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'resume_download_url' => $this->candidate?->resume_path
                ? route('v1.recruitment.applications.resume', ['application' => $this->ulid], false)
                : null,
            'status' => $displayStatus,
            'status_label' => $displayStatusLabel,
            'status_color' => $displayStatusColor,
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
                'interviewer' => $i->interviewer ? ['id' => $i->interviewer->id, 'name' => $i->interviewer->name] : null,
                'interviewer_department' => $i->interviewerDepartment ? [
                    'id' => $i->interviewerDepartment->id,
                    'code' => $i->interviewerDepartment->code,
                    'name' => $i->interviewerDepartment->name,
                ] : null,
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
                'employee_ulid' => $this->hiring->employee?->ulid,
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
