<?php

declare(strict_types=1);

namespace App\Domains\Loan\Services;

use App\Domains\Loan\Models\Loan;
use App\Domains\Loan\Models\LoanAmortizationSchedule;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Loan Payoff & Restructuring Service — Items 63 & 64.
 *
 * Early Payoff: compute remaining principal + accrued interest for full payoff.
 * Restructuring: modify remaining amortization terms with new approval.
 */
final class LoanPayoffService implements ServiceContract
{
    /**
     * Compute early payoff amount for an active loan.
     *
     * @return array{loan_id: int, remaining_principal_centavos: int, accrued_interest_centavos: int, total_payoff_centavos: int, remaining_installments: int, savings_centavos: int}
     */
    public function computePayoff(Loan $loan): array
    {
        if ($loan->status !== 'active') {
            throw new DomainException('Only active loans can be paid off early.', 'LOAN_NOT_ACTIVE', 422);
        }

        $schedules = LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->whereIn('status', ['pending', 'due'])
            ->orderBy('due_date')
            ->get();

        $remainingPrincipal = $schedules->sum('principal_centavos');
        $remainingInterest = $schedules->sum('interest_centavos');

        // For early payoff, interest is prorated to today
        $today = now();
        $accruedInterest = 0;

        $currentDue = $schedules->first();
        if ($currentDue !== null) {
            $dueDate = \Carbon\Carbon::parse($currentDue->due_date);
            $prevDate = $currentDue->due_date === $schedules->first()?->due_date
                ? \Carbon\Carbon::parse($loan->start_date ?? $loan->created_at)
                : $dueDate->copy()->subMonth();

            $daysInPeriod = max(1, $prevDate->diffInDays($dueDate));
            $daysElapsed = min($daysInPeriod, $prevDate->diffInDays($today));
            $proratedInterest = (int) round(($currentDue->interest_centavos ?? 0) * ($daysElapsed / $daysInPeriod));
            $accruedInterest = $proratedInterest;
        }

        $totalPayoff = $remainingPrincipal + $accruedInterest;

        // Savings = what you'd pay with all remaining installments minus early payoff
        $totalRemaining = $schedules->sum('total_due_centavos');
        $savings = max(0, $totalRemaining - $totalPayoff);

        return [
            'loan_id' => $loan->id,
            'remaining_principal_centavos' => (int) $remainingPrincipal,
            'accrued_interest_centavos' => $accruedInterest,
            'total_payoff_centavos' => $totalPayoff,
            'remaining_installments' => $schedules->count(),
            'savings_centavos' => $savings,
            'payoff_date' => $today->toDateString(),
        ];
    }

    /**
     * Execute early payoff — mark all remaining installments as paid.
     */
    public function executePayoff(Loan $loan, User $actor): Loan
    {
        $payoff = $this->computePayoff($loan);

        return DB::transaction(function () use ($loan, $payoff, $actor): Loan {
            // Mark all remaining schedules as paid
            LoanAmortizationSchedule::where('loan_id', $loan->id)
                ->whereIn('status', ['pending', 'due'])
                ->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

            $loan->update([
                'status' => 'fully_paid',
                'total_paid_centavos' => $loan->total_payable_centavos,
            ]);

            return $loan->fresh() ?? $loan;
        });
    }

    /**
     * Restructure a loan — modify remaining terms.
     *
     * Creates new amortization schedule for remaining balance with new terms.
     * Requires approval (handled by caller).
     *
     * @param  array{new_term_months?: int, new_annual_rate_pct?: float, reason: string}  $newTerms
     * @return array{loan_id: int, old_remaining: int, new_monthly_centavos: int, new_term_months: int}
     */
    public function restructure(Loan $loan, array $newTerms, User $actor): array
    {
        if ($loan->status !== 'active') {
            throw new DomainException('Only active loans can be restructured.', 'LOAN_NOT_ACTIVE', 422);
        }

        return DB::transaction(function () use ($loan, $newTerms, $actor): array {
            // Get remaining principal
            $remaining = (int) LoanAmortizationSchedule::where('loan_id', $loan->id)
                ->whereIn('status', ['pending', 'due'])
                ->sum('principal_centavos');

            $newMonths = $newTerms['new_term_months'] ?? 12;
            $newRate = $newTerms['new_annual_rate_pct'] ?? (float) $loan->annual_interest_rate;
            $monthlyRate = $newRate / 12 / 100;

            // Calculate new monthly payment (PMT formula)
            if ($monthlyRate > 0) {
                $monthlyPayment = (int) round(
                    $remaining * ($monthlyRate * pow(1 + $monthlyRate, $newMonths))
                    / (pow(1 + $monthlyRate, $newMonths) - 1)
                );
            } else {
                $monthlyPayment = (int) round($remaining / $newMonths);
            }

            // Delete remaining unpaid schedules
            LoanAmortizationSchedule::where('loan_id', $loan->id)
                ->whereIn('status', ['pending', 'due'])
                ->delete();

            // Generate new schedule
            $balance = $remaining;
            $startDate = now()->startOfMonth()->addMonth();

            for ($i = 1; $i <= $newMonths; $i++) {
                $interest = (int) round($balance * $monthlyRate);
                $principal = $monthlyPayment - $interest;

                if ($i === $newMonths) {
                    $principal = $balance; // Last payment covers remaining
                    $monthlyPayment = $principal + $interest;
                }

                LoanAmortizationSchedule::create([
                    'loan_id' => $loan->id,
                    'installment_number' => $i,
                    'due_date' => $startDate->copy()->addMonths($i - 1)->toDateString(),
                    'principal_centavos' => $principal,
                    'interest_centavos' => $interest,
                    'total_due_centavos' => $principal + $interest,
                    'status' => 'pending',
                ]);

                $balance -= $principal;
            }

            // Update loan record
            $loan->update([
                'monthly_amortization_centavos' => $monthlyPayment,
                'total_payable_centavos' => ($loan->total_paid_centavos ?? 0) + ($monthlyPayment * $newMonths),
                'notes' => ($loan->notes ? $loan->notes . "\n" : '') . "Restructured on " . now()->toDateString() . ": {$newTerms['reason']}",
            ]);

            return [
                'loan_id' => $loan->id,
                'old_remaining_centavos' => $remaining,
                'new_monthly_centavos' => $monthlyPayment,
                'new_term_months' => $newMonths,
                'new_rate_pct' => $newRate,
            ];
        });
    }
}
