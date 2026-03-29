<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\Attendance\Models\AttendanceLog;
use App\Domains\HR\Models\Employee;
use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\Models\PayrollRunExclusion;
use App\Domains\Payroll\StateMachines\PayrollRunStateMachine;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * Pre-Run Validation Service — Step 3 of the payroll run wizard.
 *
 * Runs all 8 PR checks and returns structured results.
 * PR-001 to PR-008 map to the design document exactly.
 *
 * Each result has:
 *   code     : 'PR-001' .. 'PR-008'
 *   label    : Human-readable title
 *   status   : 'pass' | 'block' | 'warn'
 *   message  : Explanation when status != 'pass'
 *   details  : Optional extra data (department breakdowns, etc.)
 */
final class PayrollPreRunService implements ServiceContract
{
    public function __construct(
        private readonly PayrollRunStateMachine $stateMachine,
    ) {}

    /**
     * Run all 8 checks for the given run.
     *
     * @return array{
     *   checks: list<array{code: string, label: string, status: string, message: string|null, details: mixed}>,
     *   has_blockers: bool,
     *   total_passed: int,
     * }
     */
    public function runAllChecks(PayrollRun $run): array
    {
        $checks = [
            $this->checkPR001($run),
            $this->checkPR002($run),
            $this->checkPR003($run),
            $this->checkPR004($run),
            $this->checkPR005($run),
            $this->checkPR006($run),
            $this->checkPR007($run),
            $this->checkPR008($run),
            $this->checkPR009($run),
            $this->checkPR010($run),
        ];

        $hasBlockers = collect($checks)->contains(fn ($c) => $c['status'] === 'block');
        $totalPassed = collect($checks)->filter(fn ($c) => $c['status'] === 'pass')->count();

        return [
            'checks' => $checks,
            'has_blockers' => $hasBlockers,
            'total_passed' => $totalPassed,
        ];
    }

    /** Acknowledge warnings and transition to PRE_RUN_CHECKED. */
    public function acknowledge(PayrollRun $run, int $userId): PayrollRun
    {
        // Idempotent — already acknowledged (e.g. double-click or page refresh)
        if (strtoupper((string) $run->status) === 'PRE_RUN_CHECKED') {
            return $run;
        }

        $run->pre_run_acknowledged_at = now();
        $run->pre_run_acknowledged_by_id = $userId;
        $run->pre_run_checked_at = now();
        $run->save();

        $this->stateMachine->transition($run, 'PRE_RUN_CHECKED');

        return $run->fresh();
    }

    // ── Individual checks ─────────────────────────────────────────────────────

    /**
     * PR-001: No overlapping completed run for this period and run type.
     */
    private function checkPR001(PayrollRun $run): array
    {
        $overlapping = PayrollRun::whereNotIn('status', [
            'draft', 'cancelled', 'DRAFT', 'RETURNED', 'REJECTED',
        ])
            ->where('id', '!=', $run->id)
            ->where('run_type', $run->run_type)
            ->where('cutoff_start', '<=', $run->cutoff_end)
            ->where('cutoff_end', '>=', $run->cutoff_start)
            ->exists();

        if ($overlapping) {
            return $this->fail('PR-001', 'No overlapping run for this period', 'block',
                'A completed or active run already covers this cutoff range and run type.');
        }

        return $this->pass('PR-001', 'No overlapping run for this period');
    }

    /**
     * PR-002: All in-scope employees are ACTIVE.
     */
    private function checkPR002(PayrollRun $run): array
    {
        $scopeDepts = $run->scope_departments;

        $inactiveCount = Employee::where('employment_status', '!=', 'active')
            ->when(! empty($scopeDepts), fn ($q) => $q->whereIn('department_id', $scopeDepts))
            ->where('date_hired', '<=', $run->cutoff_end)
            ->count();

        if ($inactiveCount > 0) {
            return $this->fail('PR-002', 'All in-scope employees are ACTIVE', 'warn',
                "{$inactiveCount} in-scope employees are not in ACTIVE status. They will be automatically excluded.");
        }

        $total = Employee::where('employment_status', 'active')
            ->when(! empty($scopeDepts), fn ($q) => $q->whereIn('department_id', $scopeDepts))
            ->count();

        return $this->pass('PR-002', "All {$total} in-scope employees are ACTIVE");
    }

    /**
     * PR-003: Unprocessed attendance logs for the cutoff range.
     * Unprocessed = is_processed = false (not yet validated by attendance processing).
     */
    private function checkPR003(PayrollRun $run): array
    {
        $scopeDepts = $run->scope_departments;

        $anomalyQuery = AttendanceLog::where('is_processed', false)
            ->where('work_date', '>=', $run->cutoff_start)
            ->where('work_date', '<=', $run->cutoff_end);

        if (! empty($scopeDepts)) {
            $anomalyQuery->whereHas('employee', fn ($q) => $q->whereIn('department_id', $scopeDepts));
        }

        $total = $anomalyQuery->count();

        if ($total > 0) {
            // Group by department for drill-down
            $byDept = AttendanceLog::where('is_processed', false)
                ->where('work_date', '>=', $run->cutoff_start)
                ->where('work_date', '<=', $run->cutoff_end)
                ->join('employees', 'attendance_logs.employee_id', '=', 'employees.id')
                ->join('departments', 'employees.department_id', '=', 'departments.id')
                ->select('departments.id', 'departments.name', DB::raw('count(*) as count'))
                ->groupBy('departments.id', 'departments.name')
                ->get()
                ->map(fn ($r) => ['dept_id' => $r->id, 'dept_name' => $r->name, 'count' => $r->count])
                ->all();

            return array_merge(
                $this->fail('PR-003', 'Unprocessed Attendance Logs', 'warn',
                    "{$total} attendance log(s) for {$run->cutoff_start}–{$run->cutoff_end} have not been processed yet. Review before proceeding."),
                ['details' => ['by_department' => $byDept]],
            );
        }

        return $this->pass('PR-003', 'All attendance logs processed for the period');
    }

    /**
     * PR-004: All approved leaves reconciled with attendance.
     */
    private function checkPR004(PayrollRun $run): array
    {
        // Check if there are any approved leave requests without corresponding
        // attendance records in the payroll period
        $unreconciled = DB::table('leave_requests as lr')
            ->where('lr.status', 'approved')
            ->where('lr.date_from', '<=', $run->cutoff_end)
            ->where('lr.date_to', '>=', $run->cutoff_start)
            ->whereNotExists(function ($q) use ($run) {
                $q->from('attendance_logs as al')
                    ->whereColumn('al.employee_id', 'lr.employee_id')
                    ->where('al.is_absent', true)
                    ->where('al.work_date', '>=', $run->cutoff_start)
                    ->where('al.work_date', '<=', $run->cutoff_end);
            })
            ->count();

        if ($unreconciled > 0) {
            return $this->fail('PR-004', 'All approved leaves reconciled with attendance', 'warn',
                "{$unreconciled} approved leave requests have not been reconciled with attendance records.");
        }

        return $this->pass('PR-004', 'All approved leaves reconciled with attendance');
    }

    /**
     * PR-005: Bank account details exist for all in-scope employees.
     */
    private function checkPR005(PayrollRun $run): array
    {
        $scopeDepts = $run->scope_departments;

        // Exclude employees already manually excluded from this run
        $excludedIds = PayrollRunExclusion::where('payroll_run_id', $run->id)
            ->pluck('employee_id')
            ->toArray();

        $missingQuery = Employee::where('employment_status', 'active')
            ->when(! empty($scopeDepts), fn ($q) => $q->whereIn('department_id', $scopeDepts))
            ->whereNotIn('id', $excludedIds)
            ->where(fn ($q) => $q->whereNull('bank_account_no')->orWhere('bank_account_no', ''));

        $missingBank = $missingQuery->count();

        if ($missingBank > 0) {
            $employees = (clone $missingQuery)
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code'])
                ->map(fn ($e) => [
                    'full_name' => "{$e->first_name} {$e->last_name}",
                    'employee_code' => $e->employee_code,
                ])
                ->all();

            return array_merge(
                $this->fail('PR-005', 'Bank account details for all in-scope employees', 'warn',
                    "{$missingBank} in-scope employee(s) have no bank account number and cannot be included in bank disbursement. Go back to Step 2 to exclude them, or proceed and handle payment manually."),
                ['details' => ['employees' => $employees]],
            );
        }

        return $this->pass('PR-005', 'Bank account details exist for all in-scope employees');
    }

    /**
     * PR-006: Government contribution tables are present for the cutoff period.
     */
    private function checkPR006(PayrollRun $run): array
    {
        [$year] = explode('-', $run->cutoff_start);
        $yearInt = (int) $year;

        $asOf = "{$yearInt}-12-31";
        $sssOk = DB::table('sss_contribution_tables')->where('effective_date', '<=', $asOf)->exists();
        $phOk = DB::table('philhealth_premium_tables')->where('effective_date', '<=', $asOf)->exists();
        $pagOk = DB::table('pagibig_contribution_tables')->where('effective_date', '<=', $asOf)->exists();

        if (! $sssOk || ! $phOk || ! $pagOk) {
            $missing = array_filter([
                ! $sssOk ? 'SSS' : null,
                ! $phOk ? 'PhilHealth' : null,
                ! $pagOk ? 'Pag-IBIG' : null,
            ]);

            return $this->fail('PR-006', 'Government contribution tables present', 'block',
                'Missing contribution tables for year '.$yearInt.': '.implode(', ', $missing));
        }

        return $this->pass('PR-006', 'SSS, PhilHealth, Pag-IBIG tables present for '.$yearInt);
    }

    /**
     * PR-007: TRAIN tax brackets present for the cutoff year.
     */
    private function checkPR007(PayrollRun $run): array
    {
        [$year] = explode('-', $run->cutoff_start);
        $yearInt = (int) $year;

        $taxOk = DB::table('train_tax_brackets')->where('effective_date', '<=', "{$yearInt}-12-31")->exists();

        if (! $taxOk) {
            return $this->fail('PR-007', 'TRAIN tax brackets present', 'block',
                "No TRAIN tax brackets found for year {$yearInt}. Cannot compute withholding tax.");
        }

        return $this->pass('PR-007', "TRAIN tax brackets present for {$yearInt}");
    }

    /**
     * PR-008: Payroll cutoff day matches system setting.
     */
    private function checkPR008(PayrollRun $run): array
    {
        $cutoffDay = (int) date('j', strtotime($run->cutoff_end));
        $systemDay = (int) (DB::table('system_settings')
            ->where('key', 'payroll_cutoff_day')
            ->value('value') ?? 31);

        if ($cutoffDay !== $systemDay) {
            return $this->fail('PR-008', 'Payroll cutoff day matches system setting', 'warn',
                "Cutoff end day is {$cutoffDay} but system setting is {$systemDay}. Please verify this is intentional.");
        }

        return $this->pass('PR-008', "Payroll cutoff day ({$cutoffDay}) matches system setting");
    }

    /**
     * PR-010: Minimum wage rates must be configured.
     *
     * REC-13: Without minimum wage rates, the system cannot validate
     * compliance with DOLE minimum wage requirements. This is a warning
     * (not a block) because some employees may be exempt.
     */
    private function checkPR010(PayrollRun $run): array
    {
        $cutoffStart = $run->cutoff_start;

        $hasRates = \App\Domains\Payroll\Models\MinimumWageRate::where('effective_date', '<=', $cutoffStart)
            ->exists();

        if (! $hasRates) {
            return $this->fail(
                'PR-010',
                'Minimum Wage Rates',
                'warn',
                'No minimum wage rates configured. The system cannot validate DOLE compliance. '
                . 'Seed minimum wage rates via the admin panel or MinimumWageRateSeeder.',
            );
        }

        return $this->pass('PR-010', 'Minimum wage rates present');
    }

    /**
     * PR-009: Government contribution tables must have entries.
     *
     * REC-08: Without these tables, Steps 10-14 of the payroll pipeline will
     * fail or produce zero deductions — a compliance violation.
     */
    private function checkPR009(PayrollRun $run): array
    {
        $year = (int) date('Y', strtotime($run->cutoff_start));
        $issues = [];

        if (\App\Domains\Payroll\Models\SssContributionTable::count() === 0) {
            $issues[] = 'SSS contribution table is empty';
        }

        if (\App\Domains\Payroll\Models\PhilhealthPremiumTable::count() === 0) {
            $issues[] = 'PhilHealth premium table is empty';
        }

        if (\App\Domains\Payroll\Models\PagibigContributionTable::count() === 0) {
            $issues[] = 'Pag-IBIG contribution table is empty';
        }

        if (\App\Domains\Payroll\Models\TrainTaxBracket::where('effective_year', '<=', $year)->doesntExist()) {
            $issues[] = "TRAIN tax brackets have no entries effective for year {$year}";
        }

        if (! empty($issues)) {
            return $this->fail(
                'PR-009',
                'Government Contribution Tables',
                'block',
                'Missing government contribution tables will cause pipeline failure: ' . implode('; ', $issues),
            );
        }

        return $this->pass('PR-009', 'Government contribution tables present');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function pass(string $code, string $label): array
    {
        return ['code' => $code, 'label' => $label, 'status' => 'pass', 'message' => null, 'details' => null];
    }

    private function fail(string $code, string $label, string $severity, string $message): array
    {
        return ['code' => $code, 'label' => $label, 'status' => $severity, 'message' => $message, 'details' => null];
    }
}
