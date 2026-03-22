<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sample Payroll Data Seeder for January-February 2026.
 * 
 * Creates payroll runs and computed payroll data for the first two months
 * of 2026, using the attendance data from SampleAttendanceJanFeb2026Seeder.
 * 
 * This seeder depends on:
 * - Employees being seeded
 * - Attendance data being seeded
 * - Fiscal periods existing for 2026
 * - Pay periods being configured
 */
class SamplePayrollJanFeb2026Seeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting Sample Payroll Seeding (Jan-Feb 2026)...');

        // Get HR manager for payroll operations
        $hrUser = User::where('email', 'hr.manager@ogamierp.local')->first()
            ?? User::role('hr_manager')->first()
            ?? User::first();

        if (!$hrUser) {
            $this->command->warn('  No HR user found, skipping payroll seeding.');
            return;
        }

        // Create fiscal periods for 2026 if not exists
        $this->ensureFiscalPeriods2026();

        // Create pay periods for Jan-Feb 2026
        $this->createPayPeriods2026();

        // Create sample payroll runs
        $this->createPayrollRuns($hrUser);

        $this->command->info('✓ Sample Payroll Seeding Complete!');
    }

    private function ensureFiscalPeriods2026(): void
    {
        // Check if any fiscal periods exist for 2026 (date range check)
        $exists = DB::table('fiscal_periods')
            ->where('date_from', '>=', '2026-01-01')
            ->where('date_to', '<=', '2026-12-31')
            ->exists();

        if (!$exists) {
            // Create fiscal periods for Jan-Jun 2026
            $periods = [
                ['name' => 'January 2026', 'date_from' => '2026-01-01', 'date_to' => '2026-01-31'],
                ['name' => 'February 2026', 'date_from' => '2026-02-01', 'date_to' => '2026-02-28'],
                ['name' => 'March 2026', 'date_from' => '2026-03-01', 'date_to' => '2026-03-31'],
                ['name' => 'April 2026', 'date_from' => '2026-04-01', 'date_to' => '2026-04-30'],
                ['name' => 'May 2026', 'date_from' => '2026-05-01', 'date_to' => '2026-05-31'],
                ['name' => 'June 2026', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30'],
            ];

            foreach ($periods as $index => $period) {
                DB::table('fiscal_periods')->insert([
                    'name' => $period['name'],
                    'date_from' => $period['date_from'],
                    'date_to' => $period['date_to'],
                    'status' => $index < 2 ? 'closed' : 'open', // Jan-Feb closed
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->command->info('  ✓ Created fiscal periods for 2026');
        }
    }

    private function createPayPeriods2026(): void
    {
        $periods = [
            // January 2026
            ['label' => 'Jan 2026 1st', 'cutoff_start' => '2026-01-01', 'cutoff_end' => '2026-01-15', 'pay_date' => '2026-01-20'],
            ['label' => 'Jan 2026 2nd', 'cutoff_start' => '2026-01-16', 'cutoff_end' => '2026-01-31', 'pay_date' => '2026-01-31'],
            // February 2026
            ['label' => 'Feb 2026 1st', 'cutoff_start' => '2026-02-01', 'cutoff_end' => '2026-02-15', 'pay_date' => '2026-02-20'],
            ['label' => 'Feb 2026 2nd', 'cutoff_start' => '2026-02-16', 'cutoff_end' => '2026-02-28', 'pay_date' => '2026-02-28'],
        ];

        $created = 0;
        foreach ($periods as $period) {
            $exists = DB::table('pay_periods')
                ->where('cutoff_start', $period['cutoff_start'])
                ->where('cutoff_end', $period['cutoff_end'])
                ->exists();

            if (!$exists) {
                DB::table('pay_periods')->insert([
                    'label' => $period['label'],
                    'cutoff_start' => $period['cutoff_start'],
                    'cutoff_end' => $period['cutoff_end'],
                    'pay_date' => $period['pay_date'],
                    'frequency' => 'semi_monthly',
                    'status' => 'closed', // Past periods
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            }
        }

        $this->command->info("  ✓ Created {$created} pay periods for Jan-Feb 2026");
    }

    private function createPayrollRuns(User $user): void
    {
        // Get employees
        $employees = DB::table('employees')
            ->where('employment_status', 'active')
            ->limit(10)
            ->get();

        if ($employees->isEmpty()) {
            $this->command->warn('  No active employees found, skipping payroll runs.');
            return;
        }

        $payPeriods = DB::table('pay_periods')
            ->where('cutoff_start', '>=', '2026-01-01')
            ->where('cutoff_end', '<=', '2026-02-28')
            ->get();

        $created = 0;
        foreach ($payPeriods as $payPeriod) {
            // Check if payroll run exists
            $exists = DB::table('payroll_runs')
                ->where('pay_period_id', $payPeriod->id)
                ->exists();

            if ($exists) {
                continue;
            }

            // Create payroll run
            $payrollRunId = DB::table('payroll_runs')->insertGetId([
                'ulid' => (string) Str::ulid(),
                'reference_no' => 'PR-2026-' . str_pad((string) ($created + 1), 6, '0', STR_PAD_LEFT),
                'pay_period_label' => $payPeriod->label,
                'cutoff_start' => $payPeriod->cutoff_start,
                'cutoff_end' => $payPeriod->cutoff_end,
                'pay_date' => $payPeriod->pay_date,
                'status' => 'completed',
                'created_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'locked_at' => now(),
                'notes' => 'Auto-generated sample payroll for testing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create payroll details for each employee
            foreach ($employees as $employee) {
                $this->createPayrollDetail($payrollRunId, $employee, $payPeriod);
            }

            $created++;
        }

        $this->command->info("  ✓ Created {$created} payroll runs with details");
    }

    private function createPayrollDetail(int $payrollRunId, $employee, $payPeriod): void
    {
        // Get attendance summary for this period
        $attendanceSummary = DB::table('attendance_logs')
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$payPeriod->cutoff_start, $payPeriod->cutoff_end])
            ->selectRaw('
                COUNT(*) as days_worked,
                COALESCE(SUM(late_minutes), 0) as total_late_minutes,
                COALESCE(SUM(undertime_minutes), 0) as total_undertime_minutes,
                COALESCE(SUM(overtime_minutes), 0) as total_overtime_minutes,
                COALESCE(SUM(worked_minutes), 0) as total_worked_minutes
            ')
            ->first();

        $basicMonthlyRate = $employee->basic_monthly_rate ?? 250000; // Default 25k
        $dailyRate = (int) round($basicMonthlyRate / 22);
        $hourlyRate = (int) round($dailyRate / 8);

        // Calculate semi-monthly basic pay
        $basicPay = (int) round($basicMonthlyRate / 2);

        // Calculate deductions
        $lateDeduction = (int) round(($attendanceSummary->total_late_minutes ?? 0) * ($hourlyRate / 60));
        $undertimeDeduction = (int) round(($attendanceSummary->total_undertime_minutes ?? 0) * ($hourlyRate / 60));

        // Overtime pay (1.25x rate)
        $overtimeHours = ($attendanceSummary->total_overtime_minutes ?? 0) / 60;
        $overtimePay = (int) round($overtimeHours * $hourlyRate * 1.25);

        // Government contributions (approximate values)
        $sssContribution = $this->calculateSssContribution($basicMonthlyRate);
        $philhealthContribution = (int) round($basicMonthlyRate * 0.03 / 2); // 3% shared, semi-monthly
        $pagibigContribution = 10000; // Fixed 100 PHP

        // Withholding tax (simplified calculation)
        $taxableIncome = $basicPay + $overtimePay - $lateDeduction - $undertimeDeduction;
        $withholdingTax = $this->calculateWithholdingTax($taxableIncome);

        // Net pay calculation
        $totalDeductions = $sssContribution + $philhealthContribution + $pagibigContribution + $withholdingTax;
        $netPay = $basicPay + $overtimePay - $lateDeduction - $undertimeDeduction - $totalDeductions;

        // Ensure net pay is not negative
        $netPay = max(0, $netPay);

        DB::table('payroll_details')->insert([
            'payroll_run_id' => $payrollRunId,
            'employee_id' => $employee->id,
            // Rate snapshots
            'basic_monthly_rate_centavos' => $basicMonthlyRate,
            'daily_rate_centavos' => $dailyRate,
            'hourly_rate_centavos' => $hourlyRate,
            'pay_basis' => 'monthly',
            // Attendance
            'days_worked' => $attendanceSummary->days_worked ?? 0,
            'days_late_minutes' => $attendanceSummary->total_late_minutes ?? 0,
            'undertime_minutes' => $attendanceSummary->total_undertime_minutes ?? 0,
            'overtime_regular_minutes' => $attendanceSummary->total_overtime_minutes ?? 0,
            // Earnings
            'basic_pay_centavos' => $basicPay,
            'overtime_pay_centavos' => $overtimePay,
            'gross_pay_centavos' => $basicPay + $overtimePay,
            // Government deductions
            'sss_ee_centavos' => $sssContribution,
            'philhealth_ee_centavos' => $philhealthContribution,
            'pagibig_ee_centavos' => $pagibigContribution,
            'withholding_tax_centavos' => $withholdingTax,
            // Totals
            'total_deductions_centavos' => $totalDeductions,
            'net_pay_centavos' => $netPay,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function calculateSssContribution(int $basicMonthlyRate): int
    {
        // Simplified SSS contribution table lookup
        // Based on 2024 SSS contribution table
        $monthlySalaryCredit = min($basicMonthlyRate / 100, 3000000); // Max 30,000 MSC
        
        // Employee share is approximately 4.5% of MSC
        $employeeShare = (int) round($monthlySalaryCredit * 0.045);
        
        // Semi-monthly contribution (divide by 2)
        return (int) round($employeeShare / 2);
    }

    private function calculateWithholdingTax(int $taxableIncome): int
    {
        // Simplified tax calculation based on TRAIN law
        // Annual taxable income estimate
        $annualTaxableIncome = $taxableIncome * 24; // Semi-monthly * 24

        if ($annualTaxableIncome <= 25000000) { // ₱250,000 exemption
            return 0;
        }

        $taxable = $annualTaxableIncome - 25000000;

        // Simplified tax brackets
        if ($taxable <= 40000000) {
            $annualTax = (int) round($taxable * 0.15);
        } elseif ($taxable <= 80000000) {
            $annualTax = 6000000 + (int) round(($taxable - 40000000) * 0.20);
        } elseif ($taxable <= 200000000) {
            $annualTax = 14000000 + (int) round(($taxable - 80000000) * 0.25);
        } elseif ($taxable <= 800000000) {
            $annualTax = 44000000 + (int) round(($taxable - 200000000) * 0.30);
        } else {
            $annualTax = 224000000 + (int) round(($taxable - 800000000) * 0.35);
        }

        // Convert to semi-monthly
        return (int) round($annualTax / 24);
    }
}
