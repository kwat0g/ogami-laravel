<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Enums;

enum EvaluationRecommendation: string
{
    case Endorse = 'endorse';
    case Reject = 'reject';
    case Hold = 'hold';

    public function label(): string
    {
        return match ($this) {
            self::Endorse => 'Endorse',
            self::Reject => 'Reject',
            self::Hold => 'Hold',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Endorse => 'green',
            self::Reject => 'red',
            self::Hold => 'amber',
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
