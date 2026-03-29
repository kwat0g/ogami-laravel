<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Models\ShiftSchedule;
use App\Domains\HR\Models\Employee;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the active shift schedule for an employee on a given date.
 *
 * Lookup chain: employee shift assignment → (future: department default).
 * Also provides rest-day and holiday checks.
 */
final class ShiftResolverService implements ServiceContract
{
    /**
     * Resolve the active shift schedule for an employee on a given date.
     * Uses the existing EmployeeShiftAssignment lookup.
     */
    public function resolve(Employee $employee, string $date): ?ShiftSchedule
    {
        $assignment = $employee->shiftAssignments()
            ->with('shiftSchedule')
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->latest('effective_from')
            ->first();

        return $assignment?->shiftSchedule;
    }

    /**
     * Is this date a rest day for the employee based on their shift schedule?
     *
     * Checks ShiftSchedule->work_days (comma-separated ISO weekday numbers:
     * 1=Mon, 2=Tue, ..., 7=Sun) against the date's ISO weekday.
     */
    public function isRestDay(Employee $employee, string $date): bool
    {
        $shift = $this->resolve($employee, $date);

        if (! $shift) {
            // No shift assigned — treat as no schedule, not rest day
            return false;
        }

        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso; // 1=Mon ... 7=Sun
        $workDays = array_map('intval', explode(',', $shift->work_days));

        return ! in_array($dayOfWeek, $workDays, true);
    }

    /**
     * Is this date a public holiday in the Philippine holiday calendar?
     *
     * @return object{id: int, name: string, type: string}|null
     */
    public function isHoliday(string $date): ?object
    {
        return DB::table('holiday_calendars')
            ->where('holiday_date', $date)
            ->first();
    }
}
