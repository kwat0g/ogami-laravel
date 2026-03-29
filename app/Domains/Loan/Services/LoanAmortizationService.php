<?php

declare(strict_types=1);

namespace App\Domains\Loan\Services;

use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Models\LoanAmortizationSchedule;
use App\Domains\Loan\StateMachines\LoanStateMachine;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates and manages equal-installment (straight-line) amortization schedules.
 *
 * Formula used (declining balance, monthly compounding):
 *   M = P × [r(1+r)^n] / [(1+r)^n − 1]
 *   where r = annual_rate / 12,  n = term_months
 *
 * For 0% interest loans, installment = principal / term_months.
 *
 * Rules enforced:
 *   LN-003: Total payable = principal + total interest
 *   LN-007: is_protected_by_min_wage flag may be set per installment at deduction time
 */
final class LoanAmortizationService implements ServiceContract
{
    /**
     * Generate and persist the full amortization schedule for a loan.
     * Idempotent: deletes any existing schedule for this loan first.
     *
     * @throws DomainException
     */
    public function generateSchedule(Loan $loan): void
    {
        if ($loan->principal_centavos <= 0) {
            throw new DomainException('Principal must be greater than zero.', 'LN_INVALID_PRINCIPAL', 422);
        }

        $scheduleRows = $this->buildSchedule(
            $loan->principal_centavos,
            $loan->term_months,
            $loan->interest_rate_annual,
            $loan->first_deduction_date ?? Carbon::today()->startOfMonth()->addMonth(),
        );

        DB::transaction(function () use ($loan, $scheduleRows): void {
            LoanAmortizationSchedule::where('loan_id', $loan->id)->delete();

            $totalPayable = 0;
            $monthlyAmort = 0;

            foreach ($scheduleRows as $row) {
                $totalPayable += $row['total_due_centavos'];
                $monthlyAmort = $row['total_due_centavos'];  // uniform
            }

            LoanAmortizationSchedule::insert(
                array_map(fn (array $row) => array_merge($row, [
                    'loan_id' => $loan->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]), $scheduleRows)
            );

            // Update loan header totals
            $loan->monthly_amortization_centavos = $monthlyAmort;
            $loan->total_payable_centavos = $totalPayable;
            $loan->save();
        });
    }

    /**
     * Mark an installment as paid.
     *
     * @throws DomainException
     */
    public function recordPayment(LoanAmortizationSchedule $installment, int $paidCentavos, ?int $payrollRunId = null): void
    {
        if ($installment->isPaid()) {
            throw new DomainException('Installment already paid.', 'LN_ALREADY_PAID', 422);
        }

        DB::transaction(function () use ($installment, $paidCentavos, $payrollRunId): void {
            $installment->paid_centavos += $paidCentavos;
            $installment->payroll_run_id = $payrollRunId;
            $installment->paid_date = now();
            $installment->status = $installment->paid_centavos >= $installment->total_due_centavos
                ? 'paid'
                : 'pending'; // partial payments allowed

            $installment->save();

            // Check if all installments paid → mark loan fully_paid
            $loan = $installment->loan;
            $unpaidCount = $loan->amortizationSchedules()
                ->whereNotIn('status', ['paid'])
                ->count();

            $sm = app(LoanStateMachine::class);
            if ($unpaidCount === 0) {
                $sm->transition($loan, 'fully_paid');
                $loan->save();
            } elseif ($loan->status === 'approved') {
                $sm->transition($loan, 'active');
                $loan->save();
            }
        });
    }

    /**
     * Build the raw amortization schedule rows (without loan_id / timestamps).
     *
     * @return list<array<string, mixed>>
     */
    public function buildSchedule(
        int $principalCentavos,
        int $termMonths,
        float $annualRate,
        \DateTimeInterface $firstDueDate,
    ): array {
        $rows = [];

        if ($annualRate <= 0.0) {
            // Zero-interest: flat principal split
            $baseInstallment = intdiv($principalCentavos, $termMonths);
            $remainder = $principalCentavos % $termMonths;  // add to last

            $dueDate = Carbon::instance($firstDueDate);
            $balanceAfter = $principalCentavos;

            for ($i = 1; $i <= $termMonths; $i++) {
                $principal = $baseInstallment + ($i === $termMonths ? $remainder : 0);
                $balanceAfter -= $principal;

                $rows[] = [
                    'installment_no' => $i,
                    'due_date' => $dueDate->toDateString(),
                    'principal_portion_centavos' => $principal,
                    'interest_portion_centavos' => 0,
                    'total_due_centavos' => $principal,
                    'paid_centavos' => 0,
                    'status' => 'pending',
                    'is_protected_by_min_wage' => false,
                ];

                $dueDate = $dueDate->copy()->addMonth();
            }

            return $rows;
        }

        // Declining-balance with monthly compounding
        $r = $annualRate / 12.0;
        $factor = (1 + $r) ** $termMonths;
        $monthlyPaymentExact = $principalCentavos * ($r * $factor) / ($factor - 1);
        $monthlyPayment = (int) round($monthlyPaymentExact);

        $balance = $principalCentavos;
        $dueDate = Carbon::instance($firstDueDate);

        for ($i = 1; $i <= $termMonths; $i++) {
            $interest = (int) round($balance * $r);
            $principal = $i === $termMonths
                ? $balance                           // last installment: clear the balance
                : min($monthlyPayment - $interest, $balance);

            // Ensure principal > 0 (rounding edge case guard)
            $principal = max(1, $principal);
            $total = $principal + $interest;
            $balance -= $principal;

            $rows[] = [
                'installment_no' => $i,
                'due_date' => $dueDate->toDateString(),
                'principal_portion_centavos' => $principal,
                'interest_portion_centavos' => $interest,
                'total_due_centavos' => $total,
                'paid_centavos' => 0,
                'status' => 'pending',
                'is_protected_by_min_wage' => false,
            ];

            $dueDate = $dueDate->copy()->addMonth();
        }

        return $rows;
    }
}
