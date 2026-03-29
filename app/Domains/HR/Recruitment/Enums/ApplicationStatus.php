<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Enums;

enum ApplicationStatus: string
{
    case New = 'new';
    case UnderReview = 'under_review';
    case Shortlisted = 'shortlisted';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::UnderReview => 'Under Review',
            self::Shortlisted => 'Shortlisted',
            self::Hired => 'Hired',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue-light',
            self::UnderReview => 'amber',
            self::Shortlisted => 'teal',
            self::Hired => 'green',
            self::Rejected => 'red',
            self::Withdrawn => 'gray',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::UnderReview, self::Withdrawn],
            self::UnderReview => [self::Shortlisted, self::Rejected, self::Withdrawn],
            self::Shortlisted => [self::Hired, self::Rejected, self::Withdrawn],
            self::Hired => [],
            self::Rejected => [],
            self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
