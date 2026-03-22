<?php

declare(strict_types=1);

namespace App\Domains\HR\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\EmployeeClearance;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Employee Clearance Service - Manages exit clearance workflow.
 * 
 * When an employee resigns, this service creates a checklist of clearance
 * items from each department (IT, HR, Finance, Department, Warehouse).
 * Final pay is blocked until all items are cleared.
 */
final class EmployeeClearanceService implements ServiceContract
{
    /**
     * Standard clearance checklist templates by department.
     */
    private const CLEARANCE_TEMPLATES = [
        'IT' => [
            'Return company laptop/device',
            'Return access cards/keys',
            'Account access revoked (email, systems)',
            'VPN access disabled',
            'Data backup completed',
        ],
        'HR' => [
            'Return company ID badge',
            'Exit interview completed',
            'Benefits cancellation processed',
            'COE/ Clearance form signed',
            'Handbook/ Materials returned',
        ],
        'FINANCE' => [
            'Loans/Advances cleared',
            'Final pay computed',
            'Cash accountability cleared',
            'Credit card returned (if any)',
        ],
        'DEPT' => [
            'Work handover completed',
            'Project files transferred',
            'Tools/Equipment returned',
            'Department clearance signed',
        ],
        'WAREHOUSE' => [
            'Materials accountability cleared',
            'PPE returned',
            'Warehouse access revoked',
        ],
    ];

    /**
     * Generate clearance checklist when employee resigns.
     */
    public function generateClearanceChecklist(Employee $employee, ?User $initiatedBy = null): Collection
    {
        // Check if clearance already exists
        $existingCount = EmployeeClearance::where('employee_id', $employee->id)->count();
        if ($existingCount > 0) {
            Log::info("Clearance already exists for employee {$employee->employee_code}");
            return EmployeeClearance::where('employee_id', $employee->id)->get();
        }

        $clearances = collect();

        DB::transaction(function () use ($employee, &$clearances) {
            foreach (self::CLEARANCE_TEMPLATES as $department => $items) {
                foreach ($items as $itemDescription) {
                    $clearance = EmployeeClearance::create([
                        'employee_id' => $employee->id,
                        'department_code' => $department,
                        'item_description' => $itemDescription,
                        'status' => 'pending',
                        'notes' => null,
                    ]);
                    $clearances->push($clearance);
                }
            }
        });

        Log::info("Generated {$clearances->count()} clearance items for employee {$employee->employee_code}");

        return $clearances;
    }

    /**
     * Mark a clearance item as cleared.
     */
    public function clearItem(
        EmployeeClearance $clearance,
        User $clearedBy,
        ?string $notes = null,
    ): EmployeeClearance {
        if ($clearance->status === 'cleared') {
            throw new DomainException(
                'Item already cleared',
                'CLEARANCE_ALREADY_CLEARED',
                422,
            );
        }

        $clearance->markAsCleared($clearedBy, $notes);

        Log::info("Cleared item {$clearance->id} for employee {$clearance->employee_id} by user {$clearedBy->id}");

        return $clearance->refresh();
    }

    /**
     * Mark a clearance item as blocked.
     */
    public function blockItem(
        EmployeeClearance $clearance,
        string $reason,
    ): EmployeeClearance {
        $clearance->markAsBlocked($reason);

        Log::warning("Blocked clearance item {$clearance->id} for employee {$clearance->employee_id}: {$reason}");

        return $clearance->refresh();
    }

    /**
     * Check if all clearance items are cleared for an employee.
     */
    public function isFullyCleared(int $employeeId): bool
    {
        $pendingCount = EmployeeClearance::where('employee_id', $employeeId)
            ->whereNotIn('status', ['cleared'])
            ->count();

        return $pendingCount === 0;
    }

    /**
     * Get clearance summary for an employee.
     * 
     * @return array<string, array{total: int, cleared: int, pending: int, blocked: int}>
     */
    public function getClearanceSummary(int $employeeId): array
    {
        $clearances = EmployeeClearance::where('employee_id', $employeeId)->get();

        $summary = [];
        foreach (array_keys(self::CLEARANCE_TEMPLATES) as $dept) {
            $deptItems = $clearances->where('department_code', $dept);
            $summary[$dept] = [
                'total' => $deptItems->count(),
                'cleared' => $deptItems->where('status', 'cleared')->count(),
                'pending' => $deptItems->where('status', 'pending')->count(),
                'blocked' => $deptItems->where('status', 'blocked')->count(),
                'in_progress' => $deptItems->where('status', 'in_progress')->count(),
            ];
        }

        $summary['OVERALL'] = [
            'total' => $clearances->count(),
            'cleared' => $clearances->where('status', 'cleared')->count(),
            'pending' => $clearances->where('status', 'pending')->count(),
            'blocked' => $clearances->where('status', 'blocked')->count(),
            'in_progress' => $clearances->where('status', 'in_progress')->count(),
            'is_fully_cleared' => $this->isFullyCleared($employeeId),
        ];

        return $summary;
    }

    /**
     * Get all clearance items for an employee.
     * 
     * @return Collection<int, EmployeeClearance>
     */
    public function getClearanceItems(int $employeeId, ?string $department = null): Collection
    {
        $query = EmployeeClearance::where('employee_id', $employeeId);

        if ($department !== null) {
            $query->where('department_code', $department);
        }

        return $query->orderBy('department_code')->orderBy('id')->get();
    }

    /**
     * Validate that final pay can be released.
     * Throws exception if clearance is not complete.
     */
    public function validateFinalPayRelease(int $employeeId): void
    {
        $summary = $this->getClearanceSummary($employeeId);

        if (!$summary['OVERALL']['is_fully_cleared']) {
            $blockedDepts = collect($summary)
                ->except(['OVERALL'])
                ->filter(fn ($s) => $s['blocked'] > 0 || $s['pending'] > 0)
                ->keys()
                ->implode(', ');

            throw new DomainException(
                "Cannot release final pay. Pending clearance from: {$blockedDepts}",
                'CLEARANCE_INCOMPLETE',
                422,
            );
        }
    }
}
