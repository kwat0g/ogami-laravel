<?php

declare(strict_types=1);

namespace App\Listeners\Loan;

use App\Domains\HR\Events\EmployeeResigned;
use App\Domains\Loan\Models\Loan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * F-011: When an employee separates, flag all active loans as immediately due.
 *
 * Sets separation_flagged_at on each active loan so that:
 * 1. Final pay computation can include remaining loan balance
 * 2. HR/Accounting dashboard shows loans needing settlement
 * 3. Payroll deduction stops (employee no longer in payroll scope)
 */
final class FlagActiveLoansOnSeparation implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(EmployeeResigned $event): void
    {
        $employee = $event->employee;

        $activeLoans = Loan::where('employee_id', $employee->id)
            ->whereIn('status', ['active', 'approved', 'ready_for_disbursement'])
            ->get();

        if ($activeLoans->isEmpty()) {
            return;
        }

        foreach ($activeLoans as $loan) {
            $loan->update([
                'separation_flagged_at' => now(),
                'separation_type' => $event->separationType,
                'separation_date' => $event->separationDate,
            ]);

            Log::info('[Loan] Flagged active loan for separation settlement', [
                'loan_id' => $loan->id,
                'reference_no' => $loan->reference_no,
                'employee_id' => $employee->id,
                'outstanding_balance' => $loan->outstanding_balance_centavos ?? 0,
                'separation_type' => $event->separationType,
            ]);
        }

        Log::info("[Loan] {$activeLoans->count()} active loan(s) flagged for employee separation", [
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name ?? $employee->id,
        ]);
    }
}
