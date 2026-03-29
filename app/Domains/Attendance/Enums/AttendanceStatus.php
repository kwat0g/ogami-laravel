<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Enums;

/**
 * Richer attendance status for UI display.
 *
 * These values populate the attendance_logs.attendance_status column.
 * The existing boolean flags (is_present, is_absent, etc.) remain
 * the source of truth for payroll — this enum drives the frontend
 * status badges and filtering.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Undertime = 'undertime';
    case LateAndUndertime = 'late_and_undertime';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
    case RestDay = 'rest_day';
    case OvertimeOnly = 'overtime_only';
    case OutOfOffice = 'out_of_office';
    case Pending = 'pending';
    case Corrected = 'corrected';
    case NoSchedule = 'no_schedule';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Undertime => 'Undertime',
            self::LateAndUndertime => 'Late & Undertime',
            self::Absent => 'Absent',
            self::OnLeave => 'On Leave',
            self::Holiday => 'Holiday',
            self::RestDay => 'Rest Day',
            self::OvertimeOnly => 'Overtime Only',
            self::OutOfOffice => 'Out of Office',
            self::Pending => 'Pending',
            self::Corrected => 'Corrected',
            self::NoSchedule => 'No Schedule',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Late => 'yellow',
            self::Undertime => 'orange',
            self::LateAndUndertime => 'amber',
            self::Absent => 'red',
            self::OnLeave => 'blue',
            self::Holiday => 'purple',
            self::RestDay => 'gray',
            self::OvertimeOnly => 'teal',
            self::OutOfOffice => 'pink',
            self::Pending => 'sky',
            self::Corrected => 'indigo',
            self::NoSchedule => 'slate',
        };
    }

    public function affectsPayroll(): bool
    {
        return in_array($this, [
            self::Present, self::Late, self::Undertime,
            self::LateAndUndertime, self::Absent,
            self::OutOfOffice, self::Corrected,
        ], true);
    }

    public function isExcused(): bool
    {
        return in_array($this, [
            self::OnLeave, self::Holiday, self::RestDay,
        ], true);
    }

    /**
     * Statuses that count as "present" for payroll day-counting.
     *
     * @return list<self>
     */
    public static function presentStatuses(): array
    {
        return [
            self::Present, self::Late, self::Undertime,
            self::LateAndUndertime, self::OutOfOffice, self::Corrected,
        ];
    }
}
