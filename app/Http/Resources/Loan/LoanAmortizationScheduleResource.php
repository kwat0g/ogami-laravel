<?php

declare(strict_types=1);

namespace App\Http\Resources\Loan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\Loan\Models\LoanAmortizationSchedule
 */
final class LoanAmortizationScheduleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Domains\Loan\Models\LoanAmortizationSchedule $sched */
        $sched = $this->resource;

        return [
            'id' => $sched->id,
            'loan_id' => $sched->loan_id,
            'installment_no' => $sched->installment_no,
            'due_date' => $sched->due_date->toDateString(),
            'principal_portion_centavos' => $sched->principal_portion_centavos,
            'interest_portion_centavos' => $sched->interest_portion_centavos,
            'total_due_centavos' => $sched->total_due_centavos,
            // Aliases expected by the frontend LoanScheduleEntry type
            'principal' => $sched->principal_portion_centavos,
            'interest' => $sched->interest_portion_centavos,
            'amortization' => $sched->total_due_centavos,
            'balance' => $sched->remainingCentavos(),
            'is_paid' => $sched->status === 'paid',
            'paid_at' => $sched->paid_date?->toDateString(),
            // Additional detail fields
            'paid_centavos' => $sched->paid_centavos,
            'remaining_centavos' => $sched->remainingCentavos(),
            'status' => $sched->status,
            'is_protected_by_min_wage' => $sched->is_protected_by_min_wage,
        ];
    }
}
