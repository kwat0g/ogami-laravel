<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight employee summary — used in paginated list responses.
 *
 * @mixin \App\Domains\HR\Models\Employee
 */
final class EmployeeListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Domains\HR\Models\Employee $emp */
        $emp = $this->resource;

        return [
            'id' => $emp->id,
            'ulid' => $emp->ulid,
            'employee_code' => $emp->employee_code,
            'full_name' => $emp->full_name,
            'first_name' => $emp->first_name,
            'last_name' => $emp->last_name,
            'department_id' => $emp->department_id,
            'department' => $emp->department ? [
                'id' => $emp->department->id,
                'name' => $emp->department->name,
            ] : null,
            'position' => $emp->position ? [
                'id' => $emp->position->id,
                'title' => $emp->position->title,
            ] : null,
            'employment_type' => $emp->employment_type,
            'employment_status' => $emp->employment_status,
            'salary_grade_code' => $emp->salaryGrade?->code,
            'basic_monthly_rate' => $emp->basic_monthly_rate,
            'date_hired' => $emp->date_hired->toDateString(),
            'is_active' => $emp->is_active,
            'user_roles' => $emp->user ? $emp->user->getRoleNames()->toArray() : [],
            'has_sss_no' => $emp->sss_no_hash !== null,
            'has_tin' => $emp->tin_hash !== null,
            'has_philhealth_no' => $emp->philhealth_no_hash !== null,
            'has_pagibig_no' => $emp->pagibig_no_hash !== null,
        ];
    }
}
