<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Enums;

enum CandidateSource: string
{
    case Referral = 'referral';
    case WalkIn = 'walk_in';
    case JobBoard = 'job_board';
    case Agency = 'agency';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Referral => 'Referral',
            self::WalkIn => 'Walk-in',
            self::JobBoard => 'Job Board',
            self::Agency => 'Agency',
            self::Internal => 'Internal',
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
