<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Enums;

enum CorrectionType: string
{
    case TimeIn = 'time_in';
    case TimeOut = 'time_out';
    case Status = 'status';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::TimeIn => 'Time In',
            self::TimeOut => 'Time Out',
            self::Status => 'Status',
            self::Both => 'Both (Time In & Out)',
        };
    }
}
