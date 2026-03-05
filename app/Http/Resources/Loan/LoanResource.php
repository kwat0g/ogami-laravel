<?php

declare(strict_types=1);

namespace App\Http\Resources\Loan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Loan\Models\Loan
 */
final class LoanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Domains\Loan\Models\Loan $loan */
        $loan = $this->resource;

        // Compute projected monthly amortization and total payable if not yet persisted
        // (they are 0 for pending loans; only stored after HR approval generates the schedule)
        $monthlyAmort = $loan->monthly_amortization_centavos;
        $totalPayable = $loan->total_payable_centavos;
        if ($monthlyAmort === 0 && $loan->principal_centavos > 0 && $loan->term_months > 0) {
            $principal = $loan->principal_centavos;
            $termMonths = $loan->term_months;
            $annualRate = (float) $loan->interest_rate_annual;
            if ($annualRate <= 0) {
                $monthlyAmort = (int) round($principal / $termMonths);
                $totalPayable = $principal; // exact — last installment absorbs rounding remainder
            } else {
                $r = $annualRate / 12;
                $monthlyAmort = (int) round(
                    ($principal * $r * (1 + $r) ** $termMonths) / ((1 + $r) ** $termMonths - 1)
                );
                $totalPayable = $monthlyAmort * $termMonths;
            }
        }

        return [
            'id' => $loan->id,
            'ulid' => $loan->ulid,
            'reference_no' => $loan->reference_no,
            'employee_id' => $loan->employee_id,
            'loan_type_id' => $loan->loan_type_id,
            'loan_type' => $this->whenLoaded('loanType', fn () => [
                'id' => $loan->loanType->id,
                'code' => $loan->loanType->code,
                'name' => $loan->loanType->name,
                'interest_rate_annual' => $loan->loanType->interest_rate_annual,
            ]),
            'requested_by' => $loan->requested_by,
            'principal_centavos' => $loan->principal_centavos,
            'principal_php' => $loan->principal_centavos / 100,
            'term_months' => $loan->term_months,
            'interest_rate_annual' => $loan->interest_rate_annual,
            'monthly_amortization_centavos' => $monthlyAmort,
            'monthly_amortization_php' => $monthlyAmort / 100,
            'total_payable_centavos' => $totalPayable,
            'total_payable_php' => $totalPayable / 100,
            'outstanding_balance_centavos' => $loan->outstanding_balance_centavos,
            'outstanding_balance_php' => $loan->outstanding_balance_centavos / 100,
            'loan_date' => $loan->loan_date?->toDateString(),
            'deduction_cutoff' => $loan->deduction_cutoff,
            'first_deduction_date' => $loan->first_deduction_date?->toDateString(),
            'status' => $loan->status,
            'workflow_version' => $loan->workflow_version ?? 1,
            // v2 workflow actors
            'head_noted_by' => $loan->head_noted_by,
            'head_noted_at' => $loan->head_noted_at?->toIso8601String(),
            'head_noted_remarks' => $loan->head_noted_remarks,
            'manager_checked_by' => $loan->manager_checked_by,
            'manager_checked_at' => $loan->manager_checked_at?->toIso8601String(),
            'manager_checked_remarks' => $loan->manager_checked_remarks,
            'officer_reviewed_by' => $loan->officer_reviewed_by,
            'officer_reviewed_at' => $loan->officer_reviewed_at?->toIso8601String(),
            'officer_reviewed_remarks' => $loan->officer_reviewed_remarks,
            'vp_approved_by' => $loan->vp_approved_by,
            'vp_approved_at' => $loan->vp_approved_at?->toIso8601String(),
            'vp_approved_remarks' => $loan->vp_approved_remarks,
            // v1 legacy actors
            'approved_by' => $loan->approved_by,
            'approver_name' => $this->whenLoaded('approver', fn () => $loan->approver?->name),
            'approver_remarks' => $loan->approver_remarks,
            'approved_at' => $loan->approved_at?->toIso8601String(),
            'supervisor_approved_by' => $loan->supervisor_approved_by,
            'supervisor_remarks' => $loan->supervisor_remarks,
            'supervisor_approved_at' => $loan->supervisor_approved_at?->toIso8601String(),
            'accounting_approved_by' => $loan->accounting_approved_by,
            'accounting_approver_name' => $this->whenLoaded('accountingApprover', fn () => $loan->accountingApprover?->name),
            'accounting_remarks' => $loan->accounting_remarks,
            'accounting_approved_at' => $loan->accounting_approved_at?->toIso8601String(),
            'disbursed_at' => $loan->disbursed_at?->toIso8601String(),
            'disbursed_by' => $loan->disbursed_by,
            'journal_entry_id' => $loan->journal_entry_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $loan->employee->id,
                'employee_code' => $loan->employee->employee_code,
                'full_name' => $loan->employee->full_name,
            ]),
            'purpose' => $loan->purpose,
            'created_at' => $loan->created_at->toIso8601String(),
            'updated_at' => $loan->updated_at->toIso8601String(),
        ];
    }
}
