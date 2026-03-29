<?php

declare(strict_types=1);

namespace App\Domains\Budget\Services;

use App\Domains\Budget\Models\BudgetLine;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BudgetEnforcementService
 *
 * Extends budget checking beyond just Purchase Requests to cover:
 * - Overtime approval (estimates OT cost against department budget)
 * - Maintenance work orders (parts + labor cost vs maintenance budget)
 *
 * This service estimates costs and warns (soft block) or blocks (hard block)
 * based on configuration.
 *
 * Currently only PRs have hard budget blocking. OT and maintenance use
 * soft warnings -- the approver sees a warning but can still approve.
 */
final class BudgetEnforcementService implements ServiceContract
{
    /**
     * Check if approving an overtime request would exceed the department's
     * overtime budget allocation for the current fiscal year.
     *
     * @param int $departmentId Department to check
     * @param float $estimatedOtCostCentavos Estimated OT cost in centavos
     *
     * @return array{within_budget: bool, budget_total_centavos: int, used_centavos: int, remaining_centavos: int, utilization_pct: float, warning: string|null}
     */
    public function checkOvertimeBudget(int $departmentId, float $estimatedOtCostCentavos): array
    {
        $year = (int) now()->format('Y');

        // Find overtime budget line for this department
        // Look for budget lines with "overtime" or "OT" in the account name
        $otBudgetLine = BudgetLine::query()
            ->where('fiscal_year', $year)
            ->whereHas('costCenter', fn ($q) => $q->where('department_id', $departmentId))
            ->whereHas('account', fn ($q) => $q
                ->where('name', 'LIKE', '%overtime%')
                ->orWhere('name', 'LIKE', '%OT %')
                ->orWhere('account_code', 'LIKE', '%overtime%')
            )
            ->first();

        if (! $otBudgetLine) {
            // No specific OT budget line -- check overall department budget
            $dept = Department::find($departmentId);
            if (! $dept || $dept->annual_budget_centavos <= 0) {
                // REC-03: Return within_budget=false when no budget is configured.
                // Previously returned true, allowing unlimited unbudgeted spending.
                return [
                    'within_budget' => false,
                    'budget_total_centavos' => 0,
                    'used_centavos' => 0,
                    'remaining_centavos' => 0,
                    'utilization_pct' => 0,
                    'warning' => 'No budget line configured for this department in the current fiscal year. Contact the Budget Officer to set up budget allocation.',
                    'budget_not_configured' => true,
                ];
            }

            // Use department-level budget as a fallback
            $budgetTotal = $dept->annual_budget_centavos;
            $usedTotal = $this->getDepartmentSpend($departmentId, $year);
            $remaining = max(0, $budgetTotal - $usedTotal);
            $newTotal = $usedTotal + (int) $estimatedOtCostCentavos;
            $pct = $budgetTotal > 0 ? round(($newTotal / $budgetTotal) * 100, 1) : 0;

            $withinBudget = $newTotal <= $budgetTotal;
            $warning = null;
            if (! $withinBudget) {
                $warning = "Approving this OT would push department spending to {$pct}% of annual budget.";
            } elseif ($pct > 80) {
                $warning = "Department budget utilization at {$pct}% after this approval.";
            }

            return [
                'within_budget' => $withinBudget,
                'budget_total_centavos' => $budgetTotal,
                'used_centavos' => $usedTotal,
                'remaining_centavos' => $remaining,
                'utilization_pct' => $pct,
                'warning' => $warning,
            ];
        }

        // Use specific OT budget line
        $budgetTotal = $otBudgetLine->amount_centavos;
        $usedTotal = $otBudgetLine->actual_centavos ?? 0;
        $remaining = max(0, $budgetTotal - $usedTotal);
        $newTotal = $usedTotal + (int) $estimatedOtCostCentavos;
        $pct = $budgetTotal > 0 ? round(($newTotal / $budgetTotal) * 100, 1) : 0;

        $withinBudget = $newTotal <= $budgetTotal;
        $warning = null;
        if (! $withinBudget) {
            $warning = "Approving this OT would exceed the overtime budget ({$pct}% utilized).";
        } elseif ($pct > 80) {
            $warning = "Overtime budget at {$pct}% after this approval.";
        }

        return [
            'within_budget' => $withinBudget,
            'budget_total_centavos' => $budgetTotal,
            'used_centavos' => $usedTotal,
            'remaining_centavos' => $remaining,
            'utilization_pct' => $pct,
            'warning' => $warning,
        ];
    }

    /**
     * Estimate the cost of an overtime request in centavos.
     *
     * @param int $employeeId Employee whose OT rate to use
     * @param float $hours Number of OT hours
     * @param float $multiplier OT multiplier (default 1.25 for regular OT)
     *
     * @return int Estimated OT cost in centavos
     */
    public function estimateOtCost(int $employeeId, float $hours, float $multiplier = 1.25): int
    {
        $employee = Employee::find($employeeId);
        if (! $employee) {
            return 0;
        }

        // hourly_rate is a generated column in centavos
        $hourlyRate = (float) ($employee->hourly_rate ?? 0);
        if ($hourlyRate <= 0) {
            // Fallback: calculate from monthly rate
            // Assuming 26 working days * 8 hours = 208 hours/month
            $monthlyRate = (float) ($employee->basic_monthly_rate ?? 0);
            $hourlyRate = $monthlyRate > 0 ? $monthlyRate / 208 : 0;
        }

        return (int) round($hourlyRate * $hours * $multiplier);
    }

    /**
     * Get total department spend (all budget lines) for a fiscal year.
     */
    private function getDepartmentSpend(int $departmentId, int $year): int
    {
        return (int) BudgetLine::query()
            ->where('fiscal_year', $year)
            ->whereHas('costCenter', fn ($q) => $q->where('department_id', $departmentId))
            ->sum('actual_centavos');
    }
}
