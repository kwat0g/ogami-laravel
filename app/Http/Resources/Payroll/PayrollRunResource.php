<?php

declare(strict_types=1);

namespace App\Http\Resources\Payroll;

use App\Domains\Payroll\Models\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayrollRun */
final class PayrollRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Core identifiers
            'id' => $this->id,
            'ulid' => $this->ulid,
            'reference_no' => $this->reference_no,
            'pay_period_id' => $this->pay_period_id,
            'pay_period_label' => $this->pay_period_label,
            'cutoff_start' => $this->cutoff_start,
            'cutoff_end' => $this->cutoff_end,
            'pay_date' => $this->pay_date,
            'status' => $this->status,
            'run_type' => $this->run_type,
            'notes' => $this->notes,

            // Aggregate totals (centavos)
            'total_employees' => $this->total_employees,
            'gross_pay_total' => $this->gross_pay_total_centavos,
            'gross_pay_total_centavos' => $this->gross_pay_total_centavos,
            'total_deductions' => $this->total_deductions_centavos,
            'total_deductions_centavos' => $this->total_deductions_centavos,
            'net_pay_total' => $this->net_pay_total_centavos,
            'net_pay_total_centavos' => $this->net_pay_total_centavos,

            // Actors
            'created_by' => $this->created_by,
            'initiated_by_id' => $this->initiated_by_id ?? $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'locked_at' => $this->locked_at?->toIso8601String(),

            // v1.0 workflow: scope
            'scope_departments' => $this->scope_departments,
            'scope_positions' => $this->scope_positions,
            'scope_employment_types' => $this->scope_employment_types,
            'scope_include_unpaid_leave' => (bool) $this->scope_include_unpaid_leave,
            'scope_include_probation_end' => (bool) $this->scope_include_probation_end,
            'scope_confirmed_at' => $this->scope_confirmed_at?->toIso8601String(),

            // v1.0 workflow: pre-run checks
            'pre_run_checks_json' => $this->pre_run_checks_json,
            'pre_run_acknowledged_at' => $this->pre_run_acknowledged_at?->toIso8601String(),
            'pre_run_acknowledged_by_id' => $this->pre_run_acknowledged_by_id,

            // v1.0 workflow: computation
            'computation_started_at' => $this->computation_started_at?->toIso8601String(),
            'computation_completed_at' => $this->computation_completed_at?->toIso8601String(),
            'progress_json' => $this->progress_json,

            // v1.0 workflow: approvals
            'hr_approved_by_id' => $this->hr_approved_by_id,
            'hr_approved_at' => $this->hr_approved_at?->toIso8601String(),
            'acctg_approved_by_id' => $this->acctg_approved_by_id,
            'acctg_approved_at' => $this->acctg_approved_at?->toIso8601String(),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'publish_scheduled_at' => $this->publish_scheduled_at?->toIso8601String(),

            // Exclusions (eager-loaded or empty collection)
            'exclusions' => $this->whenLoaded('exclusions', fn () => $this->exclusions->map(fn ($e) => [
                'id' => $e->id,
                'employee_id' => $e->employee_id,
                'reason' => $e->reason,
                'excluded_at' => $e->excluded_at?->toIso8601String(),
                'employee' => $e->relationLoaded('employee') ? [
                    'id' => $e->employee->id,
                    'employee_code' => $e->employee->employee_code,
                    'first_name' => $e->employee->first_name,
                    'last_name' => $e->employee->last_name,
                ] : null,
            ])
            ),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
