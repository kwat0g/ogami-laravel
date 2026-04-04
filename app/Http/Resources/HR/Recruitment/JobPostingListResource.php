<?php

declare(strict_types=1);

namespace App\Http\Resources\HR\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class JobPostingListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $department = $this->requisition?->department ?? $this->department;
        $position = $this->requisition?->position ?? $this->position;
        $salaryGrade = $this->requisition?->salaryGrade ?? $this->salaryGrade;
        $requirementItems = collect(preg_split('/\r\n|\r|\n/', (string) $this->requirements) ?: [])
            ->map(fn ($line) => trim(ltrim((string) $line, "-• \t")))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'job_requisition_id' => $this->job_requisition_id,
            'department' => $department ? [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ] : null,
            'position' => $position ? [
                'id' => $position->id,
                'code' => $position->code,
                'title' => $position->title,
            ] : null,
            'salary_grade' => $salaryGrade ? [
                'id' => $salaryGrade->id,
                'code' => $salaryGrade->code,
                'name' => $salaryGrade->name,
                'level' => $salaryGrade->level,
                'min_monthly_rate' => $salaryGrade->min_monthly_rate,
                'max_monthly_rate' => $salaryGrade->max_monthly_rate,
            ] : null,
            'headcount' => $this->headcount ?? $this->requisition?->headcount,
            'posting_number' => $this->posting_number,
            'title' => $this->title,
            'requirement_items' => $requirementItems,
            'location' => $this->location,
            'employment_type' => $this->employment_type?->value,
            'is_internal' => $this->is_internal,
            'is_external' => $this->is_external,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'published_at' => $this->published_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'views_count' => $this->views_count,
            'applications_count' => $this->whenCounted('applications'),
            'requisition' => [
                'ulid' => $this->requisition?->ulid,
                'requisition_number' => $this->requisition?->requisition_number,
                'department' => $department?->name,
                'position' => $position?->title,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
