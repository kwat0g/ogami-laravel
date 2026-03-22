<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Leave Balance Auto-Accrual Command.
 * 
 * Runs monthly to automatically accrue leave entitlements based on:
 * - Employee tenure
 * - Leave type policies
 * - Monthly accrual rates
 * 
 * Schedule: monthlyOn(1, '02:00')
 */
final class AccrueLeaveBalances extends Command
{
    protected $signature = 'leave:accrue-balances
                            {--year= : Specific year to accrue for (default: current year)}
                            {--dry-run : Show what would be accrued without saving}';

    protected $description = 'Auto-accrue monthly leave balances for all active employees';

    /**
     * Accrual configuration by leave type.
     * These could also be loaded from system_settings table.
     */
    private const ACCRUAL_CONFIG = [
        'vacation' => [
            'accrual_type' => 'monthly',
            'base_monthly_days' => 0.83, // 10 days/year ÷ 12 months
            'tenure_bands' => [
                ['years' => 0, 'multiplier' => 1.0],   // 0-4 years: 10 days/year
                ['years' => 5, 'multiplier' => 1.5],   // 5-9 years: 15 days/year
                ['years' => 10, 'multiplier' => 2.0],  // 10+ years: 20 days/year
            ],
        ],
        'sick' => [
            'accrual_type' => 'annual_reset', // Reset to fixed amount yearly
            'annual_days' => 15,
            'carryover_max' => 5,
        ],
        'sil' => [ // Service Incentive Leave
            'accrual_type' => 'anniversary',
            'min_years' => 1,
            'annual_days' => 5,
        ],
    ];

    public function handle(): int
    {
        $year = (int) $this->option('year') ?: now()->year;
        $dryRun = $this->option('dry-run');
        $asOfDate = now();

        $this->info("Processing leave accrual for year {$year} as of {$asOfDate->toDateString()}");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
        }

        $stats = [
            'employees_processed' => 0,
            'balances_created' => 0,
            'balances_updated' => 0,
            'errors' => 0,
        ];

        // Get all active employees
        $employees = Employee::where('employment_status', 'active')
            ->where('is_active', true)
            ->get();

        $this->info("Found {$employees->count()} active employees");

        foreach ($employees as $employee) {
            try {
                $result = $this->processEmployeeAccrual($employee, $year, $asOfDate, $dryRun);
                $stats['employees_processed']++;
                $stats['balances_created'] += $result['created'] ?? 0;
                $stats['balances_updated'] += $result['updated'] ?? 0;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->error("Error processing employee {$employee->employee_code}: {$e->getMessage()}");
                Log::error("Leave accrual failed for employee {$employee->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->newLine();
        $this->info('Accrual Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Employees Processed', $stats['employees_processed']],
                ['Balances Created', $stats['balances_created']],
                ['Balances Updated', $stats['balances_updated']],
                ['Errors', $stats['errors']],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Process leave accrual for a single employee.
     */
    private function processEmployeeAccrual(
        Employee $employee,
        int $year,
        Carbon $asOfDate,
        bool $dryRun,
    ): array {
        $result = ['created' => 0, 'updated' => 0];
        
        $tenureYears = $this->calculateTenureYears($employee->date_hired, $asOfDate);
        
        // Process each leave type
        foreach (self::ACCRUAL_CONFIG as $leaveTypeCode => $config) {
            $leaveType = LeaveType::where('code', $leaveTypeCode)->first();
            
            if ($leaveType === null) {
                continue; // Leave type not configured in system
            }

            $accrualAmount = $this->calculateAccrualAmount($config, $tenureYears, $employee, $asOfDate);
            
            if ($accrualAmount <= 0) {
                continue; // No accrual for this period
            }

            if ($dryRun) {
                $this->line("  [DRY-RUN] {$employee->employee_code}: {$leaveTypeCode} +{$accrualAmount} days");
                continue;
            }

            // Update or create leave balance
            $balance = LeaveBalance::firstOrNew([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
            ]);

            $isNew = !$balance->exists;
            
            if ($isNew) {
                $balance->entitled_days = $accrualAmount;
                $balance->used_days = 0;
                $balance->balance_days = $accrualAmount;
                $balance->save();
                $result['created']++;
            } else {
                // Add to existing entitlement
                $balance->entitled_days += $accrualAmount;
                $balance->balance_days += $accrualAmount;
                $balance->save();
                $result['updated']++;
            }
        }

        return $result;
    }

    /**
     * Calculate employee tenure in years.
     */
    private function calculateTenureYears(Carbon $dateHired, Carbon $asOfDate): float
    {
        return $dateHired->diffInYears($asOfDate) + 
               ($dateHired->diffInDays($asOfDate) % 365) / 365;
    }

    /**
     Calculate accrual amount based on configuration and tenure.
     */
    private function calculateAccrualAmount(
        array $config,
        float $tenureYears,
        Employee $employee,
        Carbon $asOfDate,
    ): float {
        $accrualType = $config['accrual_type'] ?? 'monthly';

        return match ($accrualType) {
            'monthly' => $this->calculateMonthlyAccrual($config, $tenureYears),
            'annual_reset' => $this->calculateAnnualResetAccrual($config, $asOfDate),
            'anniversary' => $this->calculateAnniversaryAccrual($config, $tenureYears, $employee, $asOfDate),
            default => 0,
        };
    }

    /**
     * Calculate monthly accrual with tenure multipliers.
     */
    private function calculateMonthlyAccrual(array $config, float $tenureYears): float
    {
        $baseMonthly = $config['base_monthly_days'] ?? 0;
        $multiplier = 1.0;

        // Find applicable tenure band
        foreach ($config['tenure_bands'] ?? [] as $band) {
            if ($tenureYears >= $band['years']) {
                $multiplier = $band['multiplier'];
            }
        }

        return round($baseMonthly * $multiplier, 2);
    }

    /**
     * Calculate annual reset accrual (only on January 1st).
     */
    private function calculateAnnualResetAccrual(array $config, Carbon $asOfDate): float
    {
        // Only accrue on January 1st
        if ($asOfDate->month !== 1) {
            return 0;
        }

        return $config['annual_days'] ?? 0;
    }

    /**
     * Calculate anniversary-based accrual.
     */
    private function calculateAnniversaryAccrual(
        array $config,
        float $tenureYears,
        Employee $employee,
        Carbon $asOfDate,
    ): float {
        $minYears = $config['min_years'] ?? 1;
        
        // Check if this is the anniversary month
        $isAnniversaryMonth = $asOfDate->month === $employee->date_hired->month;
        
        // Check if tenure threshold was just reached this year
        $yearsJustCompleted = floor($tenureYears);
        $isThresholdYear = $yearsJustCompleted >= $minYears;
        
        if ($isAnniversaryMonth && $isThresholdYear) {
            return $config['annual_days'] ?? 0;
        }

        return 0;
    }
}
