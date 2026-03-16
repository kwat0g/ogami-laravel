<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Domains\HR\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full employee detail resource — used for view/detail endpoints.
 * Government IDs are NEVER included in API responses (EMP-009).
 *
 * @mixin Employee
 */
final class EmployeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Employee $emp */
        $emp = $this->resource;

        return [
            'id' => $emp->id,
            'ulid' => $emp->ulid,
            'user_id' => $emp->user_id,
            'employee_code' => $emp->employee_code,
            'full_name' => $emp->full_name,
            'first_name' => $emp->first_name,
            'last_name' => $emp->last_name,
            'middle_name' => $emp->middle_name,
            'suffix' => $emp->suffix,
            'date_of_birth' => $emp->date_of_birth->toDateString(),
            'gender' => $emp->gender,
            'civil_status' => $emp->civil_status,
            'citizenship' => $emp->citizenship,
            'present_address' => $emp->present_address,
            'permanent_address' => $emp->permanent_address,
            'personal_email' => $emp->personal_email,
            'personal_phone' => $emp->personal_phone,
            'department_id' => $emp->department_id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $emp->department?->id,
                'name' => $emp->department?->name,
            ]),
            'position_id' => $emp->position_id,
            'position' => $this->whenLoaded('position', fn () => [
                'id' => $emp->position?->id,
                'title' => $emp->position?->title,
            ]),
            'salary_grade' => $this->whenLoaded('salaryGrade', fn () => [
                'id' => $emp->salaryGrade?->id,
                'code' => $emp->salaryGrade?->code,
                'name' => $emp->salaryGrade?->name,
            ]),
            'reports_to' => $emp->reports_to,
            'supervisor' => $this->whenLoaded('supervisor', fn () => [
                'id' => $emp->supervisor?->id,
                'ulid' => $emp->supervisor?->ulid,
                'full_name' => $emp->supervisor?->full_name,
                'employee_code' => $emp->supervisor?->employee_code,
            ]),
            'employment_type' => $emp->employment_type,
            'employment_status' => $emp->employment_status,
            'onboarding_status' => $emp->onboarding_status,
            'pay_basis' => $emp->pay_basis,
            'basic_monthly_rate' => $emp->basic_monthly_rate,             // centavos
            'basic_monthly_rate_php' => $emp->basic_monthly_rate / 100,       // display
            'daily_rate' => $emp->daily_rate,
            'hourly_rate' => $emp->hourly_rate,
            'date_hired' => $emp->date_hired->toDateString(),
            'regularization_date' => $emp->regularization_date?->toDateString(),
            'separation_date' => $emp->separation_date?->toDateString(),
            'is_active' => $emp->is_active,
            'bank_name' => $emp->bank_name,
            'bank_account_no' => $emp->bank_account_no,
            'notes' => $emp->notes,
            // Government ID presence flags only — never expose values
            'has_sss_no' => $emp->sss_no_hash !== null,
            'has_tin' => $emp->tin_hash !== null,
            'has_philhealth_no' => $emp->philhealth_no_hash !== null,
            'has_pagibig_no' => $emp->pagibig_no_hash !== null,
            'bir_status' => $emp->bir_status,
            'current_shift' => $this->whenLoaded(
                'shiftAssignments',
                function () use ($emp): ?array {
                    $active = $emp->shiftAssignments->first(fn ($a) => $a->isCurrentlyActive());
                    if ($active === null) {
                        return null;
                    }

                    return [
                        'id' => $active->id,
                        'shift_schedule_id' => $active->shift_schedule_id,
                        'shift_name' => $active->shiftSchedule->name,
                        'start_time' => $active->shiftSchedule->start_time,
                        'end_time' => $active->shiftSchedule->end_time,
                        'effective_from' => $active->effective_from->toDateString(),
                    ];
                }
            ),
            'created_at' => $emp->created_at->toIso8601String(),
            'updated_at' => $emp->updated_at->toIso8601String(),
        ];
    }
}
