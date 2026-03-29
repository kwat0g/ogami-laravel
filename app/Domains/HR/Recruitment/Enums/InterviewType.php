<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Enums;

enum InterviewType: string
{
    case Panel = 'panel';
    case OneOnOne = 'one_on_one';
    case Technical = 'technical';
    case HrScreening = 'hr_screening';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Panel => 'Panel Interview',
            self::OneOnOne => 'One-on-One',
            self::Technical => 'Technical Interview',
            self::HrScreening => 'HR Screening',
            self::Final => 'Final Interview',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
