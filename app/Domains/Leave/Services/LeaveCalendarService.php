<?php

declare(strict_types=1);

namespace App\Domains\Leave\Services;

use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leave Calendar Service — department-level leave map and overlap detection.
 */
final class LeaveCalendarService implements ServiceContract
{
    /**
     * Get department-level leave calendar for a date range.
     *
     * @return Collection<int, array{employee_id: int, employee_name: string, leave_type: string, start_date: string, end_date: string, status: string}>
     */
    public function departmentCalendar(int $departmentId, string $startDate, string $endDate): Collection
    {
        return DB::table('leave_requests')
            ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->where('employees.department_id', $departmentId)
            ->whereIn('leave_requests.status', ['approved', 'pending'])
            ->where('leave_requests.start_date', '<=', $endDate)
            ->where('leave_requests.end_date', '>=', $startDate)
            ->whereNull('leave_requests.deleted_at')
            ->select(
                'leave_requests.employee_id',
                DB::raw("CONCAT(employees.first_name, ' ', employees.last_name) as employee_name"),
                'leave_types.name as leave_type',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.status'
            )
            ->orderBy('leave_requests.start_date')
            ->get();
    }

    /**
     * Detect leave overlaps in a department.
     *
     * Returns dates where the number of concurrent leaves exceeds the threshold.
     *
     * @return array{overlapping_dates: array, max_concurrent: int, exceeds_threshold: bool}
     */
    public function detectOverlaps(int $departmentId, string $startDate, string $endDate, int $maxConcurrent = 2): array
    {
        $leaves = $this->departmentCalendar($departmentId, $startDate, $endDate);

        if ($leaves->isEmpty()) {
            return ['overlapping_dates' => [], 'max_concurrent' => 0, 'exceeds_threshold' => false];
        }

        // Build a date-to-count map
        $dateCounts = [];
        foreach ($leaves as $leave) {
            $current = \Carbon\Carbon::parse($leave->start_date);
            $end = \Carbon\Carbon::parse($leave->end_date);

            while ($current->lte($end)) {
                $dateStr = $current->toDateString();
                $dateCounts[$dateStr] = ($dateCounts[$dateStr] ?? 0) + 1;
                $current->addDay();
            }
        }

        $overlapping = [];
        $maxFound = 0;
        foreach ($dateCounts as $date => $count) {
            if ($count > $maxFound) {
                $maxFound = $count;
            }
            if ($count > $maxConcurrent) {
                $overlapping[] = ['date' => $date, 'concurrent_count' => $count];
            }
        }

        return [
            'overlapping_dates' => $overlapping,
            'max_concurrent' => $maxFound,
            'exceeds_threshold' => ! empty($overlapping),
        ];
    }

    /**
     * Check if a new leave request would cause an overlap violation.
     */
    public function wouldExceedLimit(int $departmentId, string $startDate, string $endDate, int $maxConcurrent = 2): bool
    {
        $result = $this->detectOverlaps($departmentId, $startDate, $endDate, $maxConcurrent - 1);

        return $result['exceeds_threshold'];
    }
}
