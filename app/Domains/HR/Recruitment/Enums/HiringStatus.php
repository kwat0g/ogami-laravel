<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Enums;

enum HiringStatus: string
{
    case Pending = 'pending';
    case Hired = 'hired';
    case FailedPreEmployment = 'failed_preemployment';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Hired => 'Hired',
            self::FailedPreEmployment => 'Failed Pre-Employment',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Hired => 'green-dark',
            self::FailedPreEmployment => 'red',
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
